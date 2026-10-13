# MyBuddy

Webapplicatie om groepsuitgaven bij te houden en eerlijk te verdelen, bijvoorbeeld voor een groepsreis, een avondje uit of een gedeeld huishouden. Gebruikers maken groepen aan, voegen betalingen toe en zien direct wie nog wat moet betalen.

Dit project is gemaakt als teamproject tijdens mijn opleiding Software Developer (Rijn IJssel).

## Screenshots

> _Plaats hier screenshots, bijvoorbeeld in een map `docs/screenshots/`:_
>
> `![Homepagina](docs/screenshots/home.png)`
> `![Groepsoverzicht](docs/screenshots/groep.png)`
> `![Betaling toevoegen](docs/screenshots/betaling-toevoegen.png)`

## Gebruikte technieken

- **PHP** met **PDO** en prepared statements
- **MySQL / MariaDB**
- **HTML, CSS en JavaScript**
- **Composer** met [PHPMailer](https://github.com/PHPMailer/PHPMailer) voor uitnodigingsmails via SMTP
- [Boxicons](https://boxicons.com/) voor iconen

## Functies

**Gebruikers**
- Registreren en inloggen
- Profiel bekijken en wachtwoord aanpassen

**Groepen**
- Groepen aanmaken, bewerken (met groepsafbeelding) en verwijderen
- Leden toevoegen, groep verlaten
- Groepsbeheerders aanwijzen of weer verwijderen
- Nieuwe gebruikers per e-mail uitnodigen met een persoonlijke registratielink

**Betalingen en schulden**
- Betalingen toevoegen, aanpassen en verwijderen, in verschillende valuta
- Kosten verdelen over de groepsleden (bijvoorbeeld "iedereen 1x" of per persoon een eigen aandeel)
- Per groep een overzicht van de leden, openstaande en betaalde schulden
- Schulden afrekenen en als betaald markeren

**Beheerder (admin)**
- Dashboard met gebruikersbeheer (deactiveren, verwijderen), alle groepen en alle transacties

## Lokaal installeren (XAMPP)

### Vereisten
- [XAMPP](https://www.apachefriends.org/) met PHP 8 of hoger
- [Composer](https://getcomposer.org/)

### Stappen

1. **Clone het project** in de `htdocs`-map van XAMPP:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/thenahomdev/mybuddy.git
   cd mybuddy
   ```

2. **Installeer de PHP-pakketten:**
   ```bash
   composer install
   ```

3. **Maak de database aan.** Start Apache en MySQL in het XAMPP Control Panel, open [phpMyAdmin](http://localhost/phpmyadmin) en importeer `database/mybuddy.sql`.
   Dit maakt de database `mybuddy` aan met alle tabellen en een lijst met valuta. Er staan geen gebruikers in.
   > Let op: bestaat de database `mybuddy` al, dan worden de tabellen daarin overschreven.

4. **Stel de configuratie in.** Kopieer de voorbeeldbestanden en vul je eigen gegevens in:
   ```bash
   copy config\connection.example.php config\connection.php
   copy config\mail.example.php config\mail.php
   ```
   - `config/connection.php`: databasegegevens (standaard XAMPP: gebruiker `root`, leeg wachtwoord)
   - `config/mail.php`: SMTP-gegevens voor de uitnodigingsmails (optioneel; voor Gmail heb je een [app-wachtwoord](https://myaccount.google.com/apppasswords) nodig)

5. Open **http://localhost/mybuddy** in je browser en maak een account aan via **Aan de slag**.

### Beheerder aanmaken

Een beheerder maak je door een bestaande gebruiker toe te voegen aan de tabel `admin`. Voer in phpMyAdmin (database `mybuddy`, tabblad SQL) uit:

```sql
INSERT INTO admin (userId) SELECT userId FROM users WHERE email = 'jouw@email.nl';
```

## Projectstructuur

```
mybuddy/
├── index.php        # Laadt de navigatie en de gevraagde pagina (?page=...)
├── inc/             # Pagina's (views) en navigatie per rol
├── php/             # Verwerking van formulieren en acties
├── config/          # Databaseverbinding en mail-instellingen (alleen voorbeeldbestanden in git)
├── database/        # Databasestructuur (mybuddy.sql)
├── css/  js/  img/  # Opmaak, scripts en afbeeldingen
└── uploads/         # Groepsafbeeldingen van gebruikers (niet in git)
```
