## Szybki start

```bash
docker-compose up -d
```

Kontenery automatycznie tworzą bazy danych, wykonują migracje i ładują dane seed/fixtures.

Dostęp do aplikacji:
- Symfony App: http://localhost:8000
- Phoenix API: http://localhost:4000

## Logowanie do Symfony App

Aplikacja nie posiada formularza logowania. Zamiast tego logowanie odbywa się przez specjalny URL:

```
http://localhost:8000/auth/{username}/{token}
```

Po załadowaniu fixtures dostępne jest gotowe konto z **predefiniowanym tokenem**:

- **Username:** `demo`
- **Token:** `demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab`

Bezpośredni link do logowania:
```
http://localhost:8000/auth/demo/demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab
```

Wylogowanie: http://localhost:8000/logout

## Testy

### Symfony

```bash
docker-compose exec symfony bin/test
```

### Phoenix

```bash
docker-compose exec -e MIX_ENV=test phoenix mix test
```
