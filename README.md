## Architektura

Ten projekt składa się z dwóch oddzielnych aplikacji z własnymi bazami danych:

- **Symfony App** (port 8000): Główna aplikacja internetowa
  - Baza danych: `symfony-db` (PostgreSQL, port 5432)
  - Nazwa bazy danych: `symfony_app`

- **Phoenix API** (port 4000): Mikroserwis REST API
  - Baza danych: `phoenix-db` (PostgreSQL, port 5433)
  - Nazwa bazy danych: `phoenix_api`

## Szybki start
```bash
docker-compose up -d

# Konfiguracja bazy danych Symfony
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console doctrine:fixtures:load --no-interaction

# Konfiguracja bazy danych Phoenix
docker-compose exec phoenix mix ecto.migrate
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

Dostęp do aplikacji:
- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

## Logowanie do Symfony App

Aplikacja nie posiada formularza logowania. Zamiast tego logowanie odbywa się przez specjalny URL:

```
http://localhost:8000/auth/{username}/{token}
```

### Konto demo

Po załadowaniu fixtures dostępne jest gotowe konto z **predefiniowanym tokenem**:

- **Username:** `demo`
- **Token:** `demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab`

Bezpośredni link do logowania:
```
http://localhost:8000/auth/demo/demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab
```

Wylogowanie: http://localhost:8000/logout

## Komendy Symfony

### Migracja bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec symfony php bin/console doctrine:schema:drop --force --full-database
docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec symfony php bin/console doctrine:fixtures:load --no-interaction
```

### Czyszczenie pamięci podręcznej (Cache)
```bash
docker-compose exec symfony php bin/console cache:clear
```

### Restart
```bash
docker-compose restart symfony
```

### Uruchamianie testów
```bash
docker-compose exec symfony php bin/phpunit
```

## Komendy Phoenix

### Migracja bazy danych
```bash
docker-compose exec phoenix mix ecto.migrate
```

### Seedowanie bazy danych
```bash
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

### Ponowne tworzenie bazy danych
```bash
docker-compose exec phoenix mix ecto.reset
docker-compose exec phoenix mix run priv/repo/seeds.exs
```

### Restart
```bash
docker-compose restart phoenix
```

### Uruchamianie testów
```bash
docker-compose exec phoenix mix test
```
