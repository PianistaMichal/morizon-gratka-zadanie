## Napotkane problemy i rozwiązania

### Błąd: kontener Symfony nie startował

Skrypt `entrypoint.sh` nie miał bitu wykonywalnego (`100644` w git zamiast `100755`).
Phoenix Dockerfile naprawiał to przez `RUN chmod +x /entrypoint.sh`, Symfony Dockerfile nie.

**Naprawa:** dodanie `RUN chmod +x /entrypoint.sh` w [symfony-app/Dockerfile](../symfony-app/Dockerfile) (stage `dev`), analogicznie jak w phoenix.

## Sposób i stopień wykorzystania AI

Do znalezienia i naprawy błędu użyłem Claude Code (claude-sonnet-4-6). AI przejrzało Dockerfiles obu serwisów, wykryło brakującą linię przez porównanie z działającym odpowiednikiem Phoenix, zaproponowało i zastosowało poprawkę, a następnie zweryfikowało ją przez pełne uruchomienie `docker-compose up -d` i przetestowanie wszystkich komend z sekcji "Szybki start" w README.
