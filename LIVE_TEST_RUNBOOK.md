# Pikado — kontrolna lista za test turnir

## Najbrži start posle paljenja Mac-a

U Terminalu pokreni:

```bash
cd /Users/vladimiralimpic/Sites/pikado
bash scripts/start-live-test.sh
```

Skripta pokreće Laravel server, queue worker, Reverb, ngrok i sprečava uspavljivanje Mac-a. Javni URL se pojavljuje pored oznake `Forwarding`. URL se obično promeni posle svakog ponovnog pokretanja ngrok-a.

Sve procese zaustavljaš jednim `Ctrl+C` u tom terminalu.

## Pre dolaska igrača

1. Poveži Mac, admin uređaj i TV na istu Wi-Fi mrežu.
2. U korenu projekta pokreni:

    ```bash
    php artisan migrate --force
    npm run build
    php artisan serve --host=0.0.0.0 --port=8000
    ```

3. U drugom terminalu pokreni red za emitovanje izmena:

    ```bash
    php artisan queue:work --tries=3 --timeout=0
    ```

4. Reverb je poželjan za trenutno osvežavanje, ali live prikaz ima rezervno automatsko osvežavanje na svakih 10 sekundi. Ako već nije pokrenut:

    ```bash
    php artisan reverb:start
    ```

5. Nemoj pokretati Vite dev server za TV test. TV treba da koristi produkcijski build iz `public/build`.
6. Proveri zdravlje aplikacije na `http://IP_ADRESA_MACA:8000/up`.
7. Otvori javni live link na TV-u i proveri da se vide logo, naziv lokala, obe table i aktuelni parovi.

## Trenutna lokalna adresa

- Aplikacija: `http://192.168.1.231:8000`
- Test live: `http://192.168.1.231:8000/t/u1x0s1ec/live`

## Trenutna javna adresa

- Aplikacija: `https://6ea0-93-86-179-231.ngrok-free.app`
- Test live: `https://6ea0-93-86-179-231.ngrok-free.app/t/u1x0s1ec/live`

Na prvom otvaranju ngrok može prikazati svoju sigurnosnu međustranicu. Klikni **Visit Site**; posle toga aplikacija se normalno otvara.

IP adresa može da se promeni posle promene Wi-Fi mreže. Na macOS-u je proveri komandom:

```bash
ipconfig getifaddr en0
```

## Tokom turnira

- Mac mora ostati uključen, priključen na punjač i bez uspavljivanja.
- Ne zatvaraj terminale u kojima rade server, queue worker i Reverb.
- Live prikaz se osvežava automatski, bez vidljive statusne oznake u zaglavlju.
- Posle svakih nekoliko rezultata proveri da se promena pojavila i na TV-u.
- Kod pogrešnog rezultata ispravi rezultat kroz admin; nemoj ručno menjati bazu.

## Brza provera ako TV ne vidi promenu

1. Sačekaj 3 sekunde.
2. Osveži stranicu na TV-u.
3. Proveri da li je `php artisan queue:work` i dalje aktivan.
4. Proveri da li Mac i TV imaju istu mrežu i da se `/up` otvara sa TV-a ili telefona.
5. Ako Reverb nije dostupan, turnir može da se nastavi — rezervno osvežavanje ostaje aktivno.

## Posle turnira

- Zaustavi procese sa `Ctrl+C` u njihovim terminalima.
- Sačuvaj kopiju baze i `storage/logs/laravel.log` pre većih naknadnih izmena.
