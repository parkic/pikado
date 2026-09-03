# Pikado produkcija na DigitalOcean-u

Ovaj setup podiže sve na jednom Ubuntu Droplet-u:

- `https://nosatipub.com` i `https://www.nosatipub.com` — statična coming-soon stranica
- `https://pikado.nosatipub.com` — Laravel/Vue aplikacija
- MariaDB — dostupna spolja samo kroz SSH tunel
- Laravel queue worker — obrađuje broadcast poslove
- Laravel Reverb — WebSocket live rezultati
- Caddy — automatski izdaje i obnavlja HTTPS sertifikate

## 1. SSH ključ na Mac-u

Proveri da li već imaš ključ:

```bash
ls -la ~/.ssh/*.pub
```

Ako nemaš ključ namenjen DigitalOcean-u, napravi ga bez prepisivanja postojećih:

```bash
ssh-keygen -t ed25519 -C "digitalocean-pikado" -f ~/.ssh/id_ed25519_digitalocean
pbcopy < ~/.ssh/id_ed25519_digitalocean.pub
```

Javni ključ koji je sada u clipboard-u dodaješ u DigitalOcean pri kreiranju Droplet-a. Privatni ključ bez `.pub` nastavka nikome ne šalješ.

## 2. Kreiranje Droplet-a

U DigitalOcean Control Panel-u izaberi **Create → Droplets** i podesi:

- Region: Frankfurt (`FRA1`)
- Image: Ubuntu 24.04 LTS x64
- Type: Basic / Regular
- Size: 2 GB RAM / 2 vCPU za početak; 4 GB RAM / 2 vCPU ako želiš više rezerve
- Authentication: SSH Key, pa dodaj javni ključ iz prethodnog koraka
- Enable improved metrics monitoring
- Enable weekly backups
- Hostname: `pikado-prod`

Sačuvaj javnu IPv4 adresu Droplet-a kao `DROPLET_IP`.

U **Networking → Firewalls** napravi firewall `pikado-production` i poveži ga sa Droplet-om. Inbound pravila:

| Tip | Port | Izvor |
| --- | ---: | --- |
| SSH | 22 | tvoja javna IP adresa, ili privremeno All IPv4/IPv6 ako se IP često menja |
| HTTP | 80 | All IPv4 i All IPv6 |
| HTTPS | 443 | All IPv4 i All IPv6 |

Ne otvaraj portove `3306`, `8080` ili `9000`.

## 3. Priprema servera

Sa lokalnog računara pošalji bootstrap skriptu i pokreni je kao root:

```bash
scp scripts/server-bootstrap.sh root@DROPLET_IP:/tmp/server-bootstrap.sh
ssh root@DROPLET_IP 'bash /tmp/server-bootstrap.sh'
```

Skripta instalira Docker iz zvaničnog repozitorijuma, pravi korisnika `deploy`, kopira mu isti SSH ključ, pravi 2 GB swap-a i isključuje SSH prijavu lozinkom.

Dodaj sledeće u `~/.ssh/config` i zameni IP:

```sshconfig
Host pikadoapp
    HostName DROPLET_IP
    User deploy
    IdentityFile ~/.ssh/id_ed25519_digitalocean
    IdentitiesOnly yes
    ServerAliveInterval 60
```

Ako koristiš neki postojeći ključ, stavi njegovu putanju u `IdentityFile`. Zatim obezbedi dozvole:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/config
```

U `~/.zshrc` dodaj traženi alias:

```bash
alias pikadoapp='ssh pikadoapp'
```

Učitaj izmenu i testiraj:

```bash
source ~/.zshrc
pikadoapp
```

SSH config je važan jer ga, za razliku od običnog shell aliasa, mogu koristiti i `scp`, `rsync` i TablePlus.

## 4. Namecheap DNS

U Namecheap-u otvori **Domain List → Manage → Advanced DNS → Host Records**. Ukloni parking ili redirect zapise koji se sudaraju sa `@`, `www` ili `pikado`, pa dodaj:

| Type | Host | Value | TTL |
| --- | --- | --- | --- |
| A Record | `@` | `DROPLET_IP` | Automatic |
| A Record | `pikado` | `DROPLET_IP` | Automatic |
| CNAME Record | `www` | `nosatipub.com` | Automatic |

Ne menjaj nameservere ako domen već koristi Namecheap BasicDNS. Ne dodaj AAAA zapis dok IPv6 nije stvarno konfigurisan na Droplet-u.

Provera propagacije:

```bash
dig +short nosatipub.com A
dig +short pikado.nosatipub.com A
```

Oba rezultata treba da budu `DROPLET_IP`. Caddy tek tada može automatski da izda HTTPS sertifikate.

## 5. Production tajne i prvi deploy

Lokalno, iz korena projekta, generiši `.env.production`:

```bash
php scripts/generate-production-env.php
```

Skripta ispisuje privremenu superadmin lozinku samo jednom. Sačuvaj je u password manager-u. `.env.production` je ignorisan u Git-u i ima dozvole `600`.

Pošalji tajne na server:

```bash
scp .env.production pikadoapp:/opt/pikado/.env.production
ssh pikadoapp 'chmod 600 /opt/pikado/.env.production'
```

Pokreni prvi deploy:

```bash
./scripts/push-production.sh --init
```

`--init` radi regularne migracije na potpuno novoj bazi i zatim pokreće zaštićeni production seed. Seed odbija da radi ako u bazi već postoji bilo koji aplikacioni podatak i nikada ništa ne briše.

Posle uspešnog prvog deploy-a baza sadrži tačno:

- jednog korisnika sa `superadmin` ulogom, prema `PIKADO_ADMIN_*` vrednostima iz lokalnog `.env.production`
- jedan lokal: `Nosati Pub`, `nosati-pub`, sa postojećim logotipom i javnim kontakt podacima
- jednu aktivnu admin vezu tog korisnika i lokala
- nula resursa, igrača, timova, turnira i mečeva

Prijava je na `https://pikado.nosatipub.com/login`. Posle prve prijave promeni privremenu lozinku. Javno registrovanje naloga je u aplikaciji isključeno.

## 6. Svaki sledeći deploy

Iz korena lokalnog projekta:

```bash
./scripts/push-production.sh
```

Skripta pre migracija pravi SQL dump baze i arhivu uploadovanih fajlova, zatim gradi nove kontejnere, pokreće migracije i proverava `/up`. Ne pokreće production seed i ne briše podatke.

Važne komande na serveru:

```bash
pikadoapp
cd /opt/pikado
docker compose -f compose.production.yaml --env-file .env.production ps
docker compose -f compose.production.yaml --env-file .env.production logs -f app queue reverb caddy
docker compose -f compose.production.yaml --env-file .env.production exec app php artisan about
docker compose -f compose.production.yaml --env-file .env.production exec app php artisan tinker
```

Ručno pravljenje backup-a:

```bash
cd /opt/pikado
./scripts/backup-production.sh
```

Backup-i se čuvaju 14 dana u `/opt/pikado/backups`. Za dodatnu zaštitu uključen je i DigitalOcean weekly backup. Backup na istom Droplet-u nije zamena za eksternu kopiju; povremeno preuzmi važan dump na lokalni računar.

Nikada na postojećoj produkciji ne pokreći `migrate:fresh`, `db:wipe`, `docker compose down -v` ili ručno brisanje Docker volume-a.

## 7. TablePlus kroz SSH tunel

U TablePlus-u napravi novu **MariaDB** konekciju.

Database deo:

| Polje | Vrednost |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3306` |
| User | vrednost `DB_USERNAME` iz lokalnog `.env.production` |
| Password | vrednost `DB_PASSWORD` iz lokalnog `.env.production` |
| Database | `pikado` |

Uključi **Over SSH**:

| Polje | Vrednost |
| --- | --- |
| Server | `DROPLET_IP` ili `pikadoapp` ako TablePlus čita SSH config |
| Port | `22` |
| User | `deploy` |
| Private key | `~/.ssh/id_ed25519_digitalocean` |

Ako ostaviš Private key prazno, uključi **Use SSH key** kako bi TablePlus pročitao `~/.ssh/config`. Obeleži produkcionu konekciju crvenom bojom i uključi Safe Mode. Baza nije javno dostupna; cela konekcija ide kroz SSH.

## 8. Provera posle puštanja

```bash
curl -I https://nosatipub.com
curl -I https://pikado.nosatipub.com/up
```

Očekivan rezultat je HTTPS i status `200`. Zatim proveri prijavu, dashboard, upload logotipa i jednu live turnir stranicu sa dva različita browser prozora kako bi potvrdio Reverb.

Password reset trenutno koristi `MAIL_MAILER=log`, pa ne šalje stvaran email. Pre nego što aplikaciju daju drugim administratorima treba povezati transakcioni email servis.

## Zvanična dokumentacija

- DigitalOcean Droplet setup: https://docs.digitalocean.com/products/droplets/getting-started/recommended-droplet-setup/
- DigitalOcean firewall: https://docs.digitalocean.com/products/networking/firewalls/how-to/configure-rules/
- Namecheap host records: https://www.namecheap.com/support/knowledgebase/article.aspx/434/2237/how-do-i-set-up-host-records-for-a-domain/
- Docker na Ubuntu-u: https://docs.docker.com/engine/install/ubuntu/
- Caddy automatic HTTPS: https://caddyserver.com/docs/automatic-https
- TablePlus SSH tunnel: https://docs.tableplus.com/gui-tools/manage-connections
