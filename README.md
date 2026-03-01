## Szybki start

```bash
docker-compose up -d
```

Kontenery automatycznie tworzą bazy danych, wykonują migracje i ładują dane seed/fixtures.

Dostęp do aplikacji:
- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

Przy pierwszym uruchomieniu odpal

```
docker-compose logs -f symfony
```

I patrz aż wszystko się zainstaluje i server http zacznie nasłuchiwać

## Logowanie do Symfony App

Otwórz http://localhost:8000/login w przeglądarce i zaloguj się danymi konta demo:

- **Username:** `demo`
- **Token:** `demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab`

Alternatywnie przez curl:

```bash
# bash / Git Bash / WSL
curl -X POST http://localhost:8000/auth \
  -d "username=demo" \
  -d "token=demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab"
```

```powershell
# PowerShell — curl to alias na Invoke-WebRequest, użyj curl.exe
curl.exe -X POST http://localhost:8000/auth -d "username=demo&token=demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab"
```

Wylogowanie: http://localhost:8000/logout

## Wsparcie IDE (autouzupełnianie klas Symfony/Doctrine)

`vendor/` żyje w Docker named volume i nie jest widoczny na hoście. Żeby IDE rozpoznawało klasy, skopiuj go z kontenera.

```bash
docker cp rekrutacja-gratka-symfony-1:/app/vendor symfony-app/vendor
```

Uruchamiaj po każdym `docker-compose exec symfony composer require/update`.

## Testy

### Symfony

```bash
docker-compose exec symfony bin/test
```

### Phoenix

```bash
docker-compose exec -e MIX_ENV=test phoenix mix test
```

## Code Quality (Symfony)

### Uruchomienie wszystkich sprawdzeń jednym poleceniem

```bash
docker-compose exec symfony bin/code_quality_checks
```

Skrypt uruchamia kolejno PHPStan i PHP CS Fixer (tryb dry-run) i zwraca kod wyjścia `1` jeśli którekolwiek narzędzie zgłosi błąd.

### Osobne narzędzia

```bash
# Statyczna analiza (poziom 8)
docker-compose exec symfony composer phpstan

# Sprawdzenie stylu kodu (bez modyfikacji)
docker-compose exec symfony composer cs-check

# Automatyczna naprawa stylu
docker-compose exec symfony composer cs-fix
```
