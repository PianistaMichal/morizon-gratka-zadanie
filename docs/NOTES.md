## Napotkane problemy i rozwiązania

### Błąd: kontener Symfony nie startował

Skrypt `entrypoint.sh` nie miał bitu wykonywalnego (`100644` w git zamiast `100755`).
Phoenix Dockerfile naprawiał to przez `RUN chmod +x /entrypoint.sh`, Symfony Dockerfile nie.

**Naprawa:** dodanie `RUN chmod +x /entrypoint.sh` w [symfony-app/Dockerfile](../symfony-app/Dockerfile) (stage `dev`), analogicznie jak w phoenix.

### Refaktor: seedowanie bazy przez Doctrine Fixtures

Komenda `app:seed` (`src/Command/SeedDatabaseCommand.php`) została zastąpiona standardowym mechanizmem Doctrine Fixtures Bundle.

**Zmiany:**
- Usunięto `symfony-app/src/Command/SeedDatabaseCommand.php`
- Dodano `symfony-app/src/DataFixtures/AppFixtures.php` z identycznymi danymi (4 użytkowników, tokeny auth, 12 zdjęć)
- Dodano `doctrine/doctrine-fixtures-bundle: ^3.5` do `require-dev` w `composer.json`

**Uruchomienie fixture:**
```bash
php bin/console doctrine:fixtures:load
```

## Sposób i stopień wykorzystania AI

Do znalezienia i naprawy błędu użyłem Claude Code (claude-sonnet-4-6). AI przejrzało Dockerfiles obu serwisów, wykryło brakującą linię przez porównanie z działającym odpowiednikiem Phoenix, zaproponowało i zastosowało poprawkę, a następnie zweryfikowało ją przez pełne uruchomienie `docker-compose up -d` i przetestowanie wszystkich komend z sekcji "Szybki start" w README.
