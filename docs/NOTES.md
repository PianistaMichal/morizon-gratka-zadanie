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

### Refaktor: kompozycja zamiast dziedziczenia w kontrolerach + warstwa serwisów

Wszystkie kontrolery usunęły `extends AbstractController` i przeszły na wstrzykiwanie zależności przez konstruktor.

**Nowe serwisy (`src/Service/`):**
- `AuthService` — walidacja tokenu i wyszukiwanie użytkownika przez Doctrine (zastępuje surowe SQL z `Connection`, eliminuje SQL injection z `AuthController`)
- `HomeService` — pobieranie zdjęć z informacją o lajkach zalogowanego użytkownika
- `PhotoLikeService` — logika toggle like/unlike (przeniesiona z `PhotoController`)
- `ProfileService` — wyszukiwanie profilu użytkownika

**Zmienione kontrolery (`src/Controller/`):**
- `AuthController` — constructor injection: `AuthService`, `RouterInterface`, `RequestStack`
- `HomeController` — constructor injection: `HomeService`, `Twig\Environment`
- `PhotoController` — constructor injection: `PhotoLikeService`, `EntityManagerInterface`, `RouterInterface`, `RequestStack`
- `ProfileController` — constructor injection: `ProfileService`, `RouterInterface`, `Twig\Environment`

**Inne zmiany:**
- Flash messages przez `$session->getBag('flashes')` (metoda na `SessionInterface`) zamiast `AbstractController::addFlash()`
- Redirect przez `new RedirectResponse($router->generate(...))` zamiast `$this->redirectToRoute()`
- Render przez `new Response($twig->render(...))` zamiast `$this->render()`
- `NotFoundHttpException` rzucany bezpośrednio zamiast `$this->createNotFoundException()`
- `config/services.yaml` — dodano alias `LikeRepositoryInterface → LikeRepository` dla autowiring `LikeService`
- `HomeController` — zmieniono adnotację `@Route` (docblock) na atrybut `#[Route]`

### Dodanie konta demo z predefiniowanym tokenem

Aby ułatwić testowanie aplikacji bez konieczności odpytywania bazy danych, dodano konto `demo` z hardcoded tokenem.

**Zmiany:**
- `symfony-app/src/DataFixtures/AppFixtures.php` — dodano usera `demo` jako pierwszy element `$usersData`; token jest teraz opcjonalnym polem `token` w tablicy (jeśli brak — generowany losowo jak dotychczas)
- `README.md` — dodano sekcję "Logowanie do Symfony App" z bezpośrednim linkiem do logowania na konto demo

**Dane konta demo:**
- Username: `demo`
- Token: `demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab`
- Login URL: `http://localhost:8000/auth/demo/demo1234567890abcdef1234567890abcdef1234567890abcdef1234567890ab`

### Refaktor: wydzielenie `addFlash` do `FlashService`

Zduplikowana prywatna metoda `addFlash` (identyczna w `AuthController` i `PhotoController`) została przeniesiona do dedykowanego serwisu.

**Nowy serwis:**
- `src/Service/FlashService` — wstrzykuje `RequestStack`, udostępnia metodę `add(string $type, string $message): void`

**Zmienione kontrolery:**
- `AuthController` — usunięto `RequestStack` i `private addFlash()`; dodano `FlashService`; wywołania zamienione na `$this->flashService->add(...)`
- `PhotoController` — analogicznie jak wyżej

### Refaktor: hardcoded strings wyciągnięte do enumów i stałych

Wszystkie powtarzające się "magic strings" zastąpione typowanymi stałymi.

**Nowe pliki:**
- `src/Enum/FlashType.php` — backed string enum (`SUCCESS`, `ERROR`, `INFO`); zastępuje literały `'success'`, `'error'`, `'info'`
- `src/Enum/LikeAction.php` — backed string enum (`LIKED`, `UNLIKED`); zastępuje literały `'liked'`, `'unliked'`
- `src/Session/SessionKey.php` — klasa z stałymi (`USER_ID`, `USERNAME`); zastępuje literały `'user_id'`, `'username'`

**Zmienione pliki:**
- `FlashService::add()` — sygnatura zmieniona z `string $type` na `FlashType $type`
- `PhotoLikeService::toggle()` — sygnatura zmieniona z `: string` na `: LikeAction`
- `AuthController`, `PhotoController`, `HomeController`, `ProfileController` — użycie powyższych stałych zamiast literałów

### Refaktor: wydzielenie pobierania userId do `SessionService`

Wzorzec `$request->getSession()->get(SessionKey::USER_ID)` był powielony w `HomeController`, `PhotoController` i `ProfileController`.

**Nowy serwis:**
- `src/Service/SessionService` — wstrzykuje `RequestStack`, udostępnia metody `getUserId(): ?int` i `login(int $userId, string $username): void`; stałe kluczy sesji (`user_id`, `username`) przeniesione z `SessionKey` jako `private const` bezpośrednio do serwisu; klasa `src/Session/SessionKey.php` usunięta

**Zmienione kontrolery:**
- `HomeController` — usunięto `SessionKey` import i `Request` parametr z akcji; dodano `SessionService`
- `PhotoController` — usunięto `SessionKey` import i `Request` parametr z akcji `like()`; dodano `SessionService`
- `ProfileController` — usunięto `SessionKey` import; dodano `SessionService`; `Request` pozostał (potrzebny do `$session->clear()`)
- `AuthController` — usunięto `SessionKey` import i `Request` parametr z akcji `login()`; dodano `SessionService`; zapis sesji przez `$this->sessionService->login(...)`

## Sposób i stopień wykorzystania AI

Do znalezienia i naprawy błędu użyłem Claude Code (claude-sonnet-4-6). AI przejrzało Dockerfiles obu serwisów, wykryło brakującą linię przez porównanie z działającym odpowiednikiem Phoenix, zaproponowało i zastosowało poprawkę, a następnie zweryfikowało ją przez pełne uruchomienie `docker-compose up -d` i przetestowanie wszystkich komend z sekcji "Szybki start" w README.
