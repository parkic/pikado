export type PublicVenueBranding = {
    name: string;
    slug: string;
    logo_url: string | null;
    description: string | null;
    address: string | null;
    phone: string | null;
    website_url: string | null;
    instagram_url: string | null;
    public_theme: 'dark' | 'light';
};

export type PublicTournamentHeaderData = {
    name: string;
    public_code: string;
    game_type: string;
    match_mode: string;
    status_label: string;
};

export type PublicTournamentPage = 'live' | 'groups' | 'schedule' | 'knockout';
