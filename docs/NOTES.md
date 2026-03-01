## Code review — poprawki (2026-03-01)

### Fix: token powiązany z userem w AuthService

`AuthService::login()` wyszukiwało token i usera osobno — można było zalogować się jako dowolny user za pomocą cudzego tokenu. Zmieniono kolejność: najpierw znajdz usera (`UserNotFoundException`), potem szukaj tokenu po `['token' => $token, 'user' => $user]` (`InvalidTokenException`). Teraz token musi należeć do konkretnego usera.

### Fix: token wyciągnięty z URL do body POST

Endpoint `/auth/{username}/{token}` (GET) zamieniony na `POST /auth` z danymi w body:
- `username` i `token` przesyłane jako pola POST, nie w ścieżce URL
- Błędy zwracają właściwe kody HTTP: 401 (zły/cudzy token), 404 (brak usera)
- Sukces: redirect do strony głównej z flash message

Token nie ląduje już w logach serwera, historii przeglądarki ani nagłówku `Referer`. `AbstractWebTestCase::loginAs()` zaktualizowane — POST do `/auth` z tokenem pobranym z bazy dla danego usera.

### Fix: stateful LikeRepository → stateless

Usunięto `setUser()`/`$user` property z `LikeRepository`. Wszystkie metody interfejsu teraz przyjmują `User $user` jako parametr: `hasUserLikedPhoto(User, Photo)`, `createLike(User, Photo)`, `unlikePhoto(User, Photo)`. `PhotoLikeService` przekazuje usera bezpośrednio do metod, bez pośredniego settera. Zmieniono `LikeRepositoryInterface` i `LikeService::execute(User, Photo)`.

### Fix: spójna case-insensitivity filtrów

`PhotoRepository::findAllWithUsersFiltered()` stosował `LOWER()` tylko dla `description`. Ujednolicono — `location` i `camera` także przez `LOWER(p.X) LIKE LOWER(:X)`.

### Fix: Prod Dockerfile bez dev dependencies

Stage `prod` instalował wszystkie zależności (`composer install`). Zmieniono na `composer install --no-dev --optimize-autoloader --no-interaction` — PHPUnit, PHPStan i CS Fixer nie trafiają na produkcję.

### Fix: LikeService przekazuje previous exception

```php
// przed:
throw new Exception('Something went wrong...');
// po:
throw new RuntimeException('Something went wrong...', 0, $e);
```
Stack trace oryginalnego wyjątku jest teraz zachowany dla debugowania.

### Dodanie PhoenixTokenType (FormType z walidacją)

Formularz zapisu tokenu PhoenixApi korzysta teraz z `PhoenixTokenType` (`src/Form/PhoenixTokenType.php`) z constraint `Length(max: 255)`. Automatyczna ochrona CSRF (`csrf_token_id: 'phoenix_token'`). `ProfileController::saveToken()` wywołuje `$form->isValid()` przed zapisem. Szablon profilu używa `form_start(tokenForm)` + `form_widget(tokenForm.phoenix_token)`.

### Fix: timeout w PhoenixClient

Dodano `'timeout' => 10.0` do żądania HTTP — bez timeoutu wolny Phoenix API mógł zawiesić obsługę requesta na czas nieokreślony.

### Dodanie indeksów na kolumnach filtrowalnych

Nowa migracja `Version20260301120000.php`:
- `idx_photos_taken_at` — dla zapytań zakresowych (`takenAt >= x AND takenAt < y`)
- `idx_photos_location`, `idx_photos_camera` — B-tree (użyteczne dla prefix LIKE; dla `%term%` trzeba `pg_trgm` GIN — dodano komentarz w migracji)
- `idx_users_username` — dla JOIN filtrowania po username

### Dodanie strony logowania (GET /login)

Dodano formularz logowania dostępny w przeglądarce po przeniesieniu tokenu do body POST.

**Nowe/zmienione pliki:**
- `symfony-app/templates/auth/login.html.twig` — nowy szablon: formularz POST do `/auth` z polami `username` i `token`
- `symfony-app/src/Controller/AuthController.php` — nowa trasa `GET /login` (`auth_login_form`); dodano wstrzykiwanie `Twig\Environment`
- `symfony-app/templates/base.html.twig` — dla niezalogowanych użytkowników wyświetlany jest przycisk 🔑 prowadzący do `/login`

**Cel:** wcześniejszy endpoint `/auth` obsługiwał tylko `POST` — nie było jak zalogować się przez przeglądarkę.

---

## Sposób i stopień wykorzystania AI

Korzystałem z Claude (Anthropic) jako narzędzia wspomagającego — głównie do:
- generowania boilerplate'u (testy jednostkowe, migracje Doctrine, struktury klas)
- weryfikowania poprawności konfiguracji (PHPStan, PHP CS Fixer, docker-compose healthchecks)
- szybkiego sprawdzania składni Elixir/OTP, którą znam słabiej niż PHP

Wszystkie decyzje architektoniczne podejmowałem samodzielnie: wybór warstwy serwisów zamiast grubych kontrolerów, rezygnacja z Hexagonal Architecture na rzecz prostszej struktury katalogów spójnej z resztą projektu, projektowanie rate-limitera jako GenServera z sliding-window. Każdy wygenerowany fragment kodu czytałem, rozumiałem i często przerabiałem — szczególnie w miejscach gdzie AI proponowało nadmiarową złożoność (np. pierwotna propozycja rate-limitera używała ETS zamiast stanu GenServera; uprościłem).

Traktuję AI jak para-programistę z dużą pamięcią składniową i zerowym kontekstem biznesowym — przydatny do pisania, bezużyteczny do myślenia.

### Fix: testy ProfileControllerTest — CSRF i format danych formularza

Dwa testy (`testSaveTokenShowsSuccessFlash`, `testImportWithValidTokenImportsPhotos`) nie przechodziły, bo:
1. CSRF nie było wyłączone w env testowym — formularz `PhoenixTokenType` odrzucał każde żądanie testowe
2. Testy wysyłały dane w formacie płaskim (`['phoenix_token' => 'value']`), ale Symfony Form oczekuje zagnieżdżonego (`['phoenix_token' => ['phoenix_token' => 'value']]`)

**Zmiany:**
- `config/packages/test/framework.yaml` — dodano `csrf_protection: false`
- `tests/Functional/ProfileControllerTest.php` — wszystkie POSTy do `/profile/token` zaktualizowane do zagnieżdżonego formatu

---

## Rzeczy, które zrobiłbym inaczej mając więcej czasu

**Autentykacja przez token w URL** to największy dług projektowy, który odziedziczyłem i pozostawiłem bez zmian. Token w ścieżce (`/auth/{username}/{token}`) ląduje w logach serwera, historii przeglądarki i nagłówku `Referer`. Docelowo: POST z tokenem w body lub standardowa sesja cookie z CSRF.

**Brak walidacji danych wejściowych na poziomie DTOs/FormTypes.** Filtry z GET są przekazywane bezpośrednio do QueryBuildera — PDO parametryzuje zapytania, więc SQL injection nie grozi, ale brak walidacji długości/formatu parametrów to luźny kontrakt. Dodałbym `Assert` Symfony lub dedykowane DTO z walidacją.

**Testy integracyjne PhoenixApi są w rzeczywistości testami kontrolera z mockiem** — nie testują faktycznego klienta HTTP (`PhoenixClient`). Brakuje testów z Bypass (Elixir) lub podobnym HTTP stubbem po stronie PHP (np. `php-http/mock-client`), które weryfikowałyby parsowanie odpowiedzi i obsługę błędów HTTP.

**Rate-limiter przechowuje stan w pamięci GenServera** — przy restarcie procesu liczniki zerują się. W produkcji użyłbym Redis (Redix) lub ETS z tabelą `:public` żyjącą poza GenServerem, żeby restart nie resetował limitów.

**Brak paginacji** na stronie głównej. Przy 12 zdjęciach z fixtures to niewidoczny problem, ale `findAllWithUsersFiltered()` robi `SELECT *` bez `LIMIT`.

---

## Propozycje usprawnień, których nie zdążyłem zaimplementować

- **CQRS-lite dla importu zdjęć** — `ProfileController::import()` robi HTTP request synchronicznie w trakcie obsługi żądania webowego. Przy wolnym PhoenixApi użytkownik czeka. Lepiej: Symfony Messenger + worker, import w tle, wynik przez polling lub Server-Sent Events.
- **Indeksy na `photos.taken_at`, `photos.location`, `photos.camera`** — filtrowanie robi `LIKE '%term%'` bez indeksów. Dla małego datasetu nieistotne, przy większej skali — full table scan.
- **Separacja środowisk w docker-compose** — aktualnie jeden plik łączy dev i prod. `docker-compose.override.yml` dla volume'ów deweloperskich i bind-mountów; bazowy `docker-compose.yml` tylko dla prod.
- **OpenAPI/Swagger dla PhoenixApi** — brak dokumentacji kontraktu między serwisami. `PhoenixClient` jest ścisło powiązany z kształtem odpowiedzi API; każda zmiana w Phoenix wymaga ręcznej synchronizacji.
- **Logi strukturalne** — obecnie Symfony loguje do `var/log/` (w named volume, niedostępne z hosta bez `docker exec`). Warto przekierować na stdout i zebrać np. Loki + Grafana lub chociaż ujednolicić format JSON.

---

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

### Refaktor: custom exceptions w AuthService + try/catch w kontrolerze

Logika walidacyjna przeniesiona z `AuthController` do `AuthService` z użyciem custom exceptions.

**Nowe pliki:**
- `src/Exception/InvalidTokenException.php` — `RuntimeException` rzucany gdy token nie istnieje w bazie; HTTP status code 401 w message code
- `src/Exception/UserNotFoundException.php` — `RuntimeException` rzucany gdy username nie istnieje; przyjmuje `$username` do czytelnego komunikatu; HTTP 404

**Zmiany w `AuthService`:**
- Usunięto `validateToken()` i `findUserByUsername()`
- Dodano `login(string $username, string $token): User` — wykonuje obie operacje, rzuca odpowiednie exceptions zamiast zwracać `bool`/`null`

**Zmiany w `AuthController::login()`:**
- Zastąpiono dwa bloki `if` jednym `try/catch`
- `InvalidTokenException` → `Response(..., 401)`
- `UserNotFoundException` → `Response(..., 404)`

**Cel:** kontroler odpowiada tylko za mapowanie na HTTP response; logika biznesowa i warunki błędów należą do serwisu.

### Dodanie testów funkcjonalnych (WebTestCase)

Dodano testy funkcjonalne dla wszystkich 4 kontrolerów Symfony.

**Nowe pliki:**
- `symfony-app/tests/AbstractWebTestCase.php` — bazowa klasa: `loadFixtures()` przez `ORMExecutor + ORMPurger (DELETE)`, helper `loginAs(string $username)` i `getFirstPhoto()`
- `symfony-app/tests/Functional/AuthControllerTest.php` — 6 testów: poprawne logowanie, zły token (401), zły username (404), wylogowanie + czyszczenie sesji
- `symfony-app/tests/Functional/HomeControllerTest.php` — 4 testy: status 200, zawartość zdjęć z fixtures, dostępność dla niezalogowanego i zalogowanego
- `symfony-app/tests/Functional/PhotoControllerTest.php` — 7 testów: wymóg logowania, like/unlike z flash messages, sprawdzenie `likeCounter` w bazie, multiple users
- `symfony-app/tests/Functional/ProfileControllerTest.php` — 4 testy: redirect bez sesji, 200 po zalogowaniu, zawartość danych usera, redirect po logout

**Wymagania przed uruchomieniem testów (w kontenerze Docker):**
```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/phpunit
```

**Uwagi:**
- Token demo znany z fixtures: `demo1234567890abcdef...` — używany w `loginAs()` dla wszystkich userów (AuthService nie sprawdza powiązania tokenu z userem)
- Fixtures ładowane są w `setUp()` każdego testu → pełna izolacja stanu DB
- IDs zdjęć nie są hardcodowane — testy pytają bazę o `findOneBy([], ['id' => 'ASC'])`

### Zadanie 2: Import zdjęć z PhoenixApi

Dodano możliwość ręcznego wpisania tokenu dostępu do PhoenixApi w profilu użytkownika oraz importowania zdjęć z PhoenixApi do SymfonyApp.

**Nowe pliki:**
- `src/Domain/Port/PhoenixClientInterface.php` — interfejs port (Hexagonal Architecture): metoda `getPhotos(string $token): array`
- `src/Infrastructure/Http/PhoenixClient.php` — implementacja HTTP: wysyła żądanie `GET /api/photos` z nagłówkiem `access-token`, rzuca `InvalidPhoenixTokenException` przy 401
- `src/Exception/InvalidPhoenixTokenException.php` — wyjątek rzucany przy błędnym tokenie PhoenixApi
- `migrations/Version20260301000000.php` — migracja: dodanie kolumny `phoenix_token VARCHAR(255) NULL` do tabeli `users`

**Zmienione pliki:**
- `src/Entity/User.php` — pole `phoenixToken` (nullable string) z getterem/setterem
- `src/Service/ProfileService.php` — metody `savePhoenixToken(User, string)` i `importPhotos(User, PhoenixClientInterface): int`
- `src/Controller/ProfileController.php` — dwie nowe trasy POST: `/profile/token` (zapis tokenu) i `/profile/import` (import zdjęć); wstrzyknięto `FlashService` i `PhoenixClientInterface`
- `templates/profile/index.html.twig` — sekcja "PhoenixApi — Import zdjęć": formularz do wpisania tokenu (z aktualną wartością), przycisk importu, status tokenu
- `config/services.yaml` — parametr `phoenix_base_url` z env `PHOENIX_BASE_URL`; binding `PhoenixClientInterface → PhoenixClient`
- `tests/Controller/ProfileControllerTest.php` — 7 nowych testów: zapis tokenu, redirect bez sesji, import z brakiem tokenu, błędny token, poprawny token; mock `PhoenixClientInterface` jako `synthetic` service

**Architektura:**
- `PhoenixClient` jest `synthetic: true` w `services_test.yaml` — testy ustawiają mock via `static::getContainer()->set(PhoenixClient::class, $mock)` przed żądaniem
- URL PhoenixApi konfigurowany przez zmienną środowiskową `PHOENIX_BASE_URL` (w docker-compose: `http://phoenix:4000`)

### Zadanie 3: Filtrowanie zdjęć na stronie głównej

Dodano możliwość filtrowania zdjęć na stronie głównej po polach: `location`, `camera`, `description`, `taken_at`, `username`.

**Zmienione pliki:**
- `src/Repository/PhotoRepository.php` — nowa metoda `findAllWithUsersFiltered(array $filters): array`; QueryBuilder z opcjonalnymi klauzulami `LIKE` dla tekstowych pól oraz filtrowanie po dacie (`takenAt >= start AND takenAt < nextDay`) zamiast funkcji `DATE()`
- `src/Service/HomeService.php` — `getPhotosData()` przyjmuje teraz `array $filters = []` i przekazuje do repozytorium
- `src/Controller/HomeController.php` — akcja `index()` przyjmuje `Request`, odczytuje parametry GET (`location`, `camera`, `description`, `taken_at`, `username`), filtruje puste wartości, przekazuje filtry do serwisu i do szablonu
- `templates/home/index.html.twig` — dodano pasek filtrów (formularz GET) powyżej siatki zdjęć; formularz zachowuje aktywne wartości między żądaniami; link "Clear" zeruje filtry

**Szczegóły implementacji:**
- Wyszukiwanie tekstowe: `LIKE '%term%'` (case-insensitive zależy od collation bazy)
- Filtrowanie daty: zakres `[date 00:00:00, date+1 00:00:00)` bez custom DQL functions
- Puste filtry są pomijane (brak efektu na zapytanie) dzięki `array_filter()` w kontrolerze

**Testy:**
- `tests/Controller/HomeControllerFilterTest.php` — 14 testów: obecność formularza, filtr po każdym polu, filtr łączony (camera + username), pusty filtr = brak efektu, zły format daty ignorowany, zachowanie wartości w formularzu po filtrowaniu

### Refaktor: użycie stałych `Response::HTTP_*` zamiast magic numbers w kontrolerach

W `AuthController` zastąpiono literały `401` i `404` stałymi Symfony:
- `401` → `Response::HTTP_UNAUTHORIZED`
- `404` → `Response::HTTP_NOT_FOUND`

**Cel:** czytelność i unikanie magic numbers.

### Refaktor: rozproszenie katalogu Likes/ do standardowych warstw

Katalog `src/Likes/` mieszał entity, repozytorium, interfejs i serwis w jednym miejscu, co było niespójne z resztą projektu.

**Zmiany:**
- `src/Likes/Like.php` → `src/Entity/Like.php` (namespace `App\Entity`)
- `src/Likes/LikeRepository.php` → `src/Repository/LikeRepository.php` (namespace `App\Repository`)
- `src/Likes/LikeRepositoryInterface.php` → `src/Repository/LikeRepositoryInterface.php` (namespace `App\Repository`)
- `src/Likes/LikeService.php` → `src/Service/LikeService.php` (namespace `App\Service`)
- Usunięto katalog `src/Likes/`
- Zaktualizowano `use` w `HomeService` i `PhotoLikeService`
- Zaktualizowano alias `LikeRepositoryInterface` w `config/services.yaml`
- Usunięto osobny mapping `Likes` z `config/packages/doctrine.yaml` — `Like` objęty istniejącym mappingiem `App\Entity`

**Cel:** spójność — każda warstwa w swoim katalogu (`Entity/`, `Repository/`, `Service/`).

### Refaktor: usunięcie DDD-owej struktury Domain/Port i Infrastructure/Http

`PhoenixClientInterface` i `PhoenixClient` były umieszczone w katalogach `Domain/Port/` i `Infrastructure/Http/` wzorowanych na Hexagonal Architecture, która nie jest stosowana nigdzie indziej w projekcie.

**Zmiany:**
- `src/Domain/Port/PhoenixClientInterface.php` → `src/Service/PhoenixClientInterface.php` (namespace `App\Service`)
- `src/Infrastructure/Http/PhoenixClient.php` → `src/Service/PhoenixClient.php` (namespace `App\Service`)
- Usunięto katalogi `src/Domain/` i `src/Infrastructure/`
- Zaktualizowano `use` w `ProfileController` i `ProfileService`
- Zaktualizowano `config/services.yaml` i `config/services_test.yaml`

**Cel:** spójność — wszystkie serwisy aplikacji w `src/Service/`.

### Dodanie testów jednostkowych (Unit Tests) dla serwisów Symfony

Dodano testy jednostkowe (PHPUnit `TestCase`, bez bazy danych) pokrywające wszystkie serwisy aplikacji.

**Zmienione pliki:**
- `symfony-app/src/Repository/LikeRepository.php` — usunięto `final` (umożliwia mockowanie przez PHPUnit)

**Nowe pliki (`symfony-app/tests/Unit/Service/`):**
- `AuthServiceTest.php` — 3 testy: rzucenie `InvalidTokenException`, `UserNotFoundException`, poprawne wywołanie `SessionService::login()`
- `LikeServiceTest.php` — 2 testy: wywołanie `createLike` + `updatePhotoCounter`, propagacja wyjątku repozytorium
- `ProfileServiceTest.php` — 6 testów: `findUser` (znaleziony / nie znaleziony), `savePhoenixToken` (wartość / pusty string → null), `importPhotos` (zlicha i tworzy Photo, 0 przy pustej tablicy)
- `PhotoLikeServiceTest.php` — 3 testy: `NotFoundHttpException` gdy brak zdjęcia, unlike gdy już polubione, like gdy jeszcze nie polubione
- `HomeServiceTest.php` — 4 testy: brak danych usera gdy `userId=null`, lajki usera gdy zalogowany, brak lajków gdy user nie znaleziony w DB, przekazanie filtrów do repozytorium
- `SessionServiceTest.php` — 4 testy: `getUserId` (wartość / null), `login` ustawia `user_id` i `username`, `logout` czyści sesję

**Uruchamianie testów jednostkowych (bez DB):**
```bash
php bin/phpunit tests/Unit
```

### Refaktor: zastąpienie luźnych porównań (`!$var`) ścisłymi (`=== null`)

Wszystkie wyrażenia w stylu `if (!$var)` na zmiennych nullable zastąpiono ścisłymi porównaniami z `null`.

**Zmienione pliki:**
- `src/Controller/ProfileController.php` — 5 zmian: `!$userId` → `$userId === null`, `!$user` → `$user === null` (3×), `!$user->getPhoenixToken()` → `$user->getPhoenixToken() === null`
- `src/Controller/PhotoController.php` — 1 zmiana: `!$userId` → `$userId === null`
- `src/Service/PhotoLikeService.php` — 1 zmiana: `!$photo` → `$photo === null`

**Cel:** unikanie niezamierzonego zachowania przy wartościach falsy (`0`, `""`) które nie są `null`; spójność z `declare(strict_types=1)` obecnym we wszystkich plikach.

### Dodanie PHPStan i PHP CS Fixer

Dodano narzędzia do statycznej analizy i formatowania kodu z profesjonalną konfiguracją.

**Nowe pakiety (`require-dev`):**
- `phpstan/phpstan: ^1.10` — statyczna analiza PHP
- `phpstan/phpstan-symfony: ^1.3` — rozszerzenie Symfony (wnioskowanie typów z kontenera, routingu itp.)
- `phpstan/extension-installer: ^1.3` — automatyczna rejestracja rozszerzeń PHPStan
- `friendsofphp/php-cs-fixer: ^3.40` — automatyczne formatowanie kodu

**Nowe pliki konfiguracyjne:**
- `symfony-app/phpstan.neon` — analiza na poziomie 8 (maksymalny), ścieżki `src/` i `tests/`, konfiguracja Symfony container XML
- `symfony-app/.php-cs-fixer.php` — reguły `@PSR12`, `@Symfony`, `@Symfony:risky`, `declare_strict_types`, ordered imports, trailing commas, brak yoda-style

**Nowy skrypt:**
- `symfony-app/bin/code_quality_checks` — uruchamia PHPStan i PHP CS Fixer (dry-run) sekwencyjnie; kolorowe wyjście; zwraca niezerowy kod wyjścia jeśli którekolwiek narzędzie zgłosi błąd

**Skrypty Composer (dla wygody):**
- `composer phpstan` — uruchamia PHPStan
- `composer cs-check` — sprawdza styl bez modyfikacji (`--dry-run --diff`)
- `composer cs-fix` — naprawia styl automatycznie
- `composer quality` — uruchamia oba: phpstan + cs-check

**Uwaga:** po `composer update` wewnątrz kontenera cache Symfony (`var/cache/dev/`) musi istnieć, by PHPStan mógł analizować kontener (wymaga wcześniejszego `bin/console cache:warmup --env=dev`).

### Refaktor: zmiana reguł PHP CS Fixer (native_function_invocation + global_namespace_import)

**Zmiany w `.php-cs-fixer.php`:**
- Usunięto regułę `native_function_invocation` — nie ma już wymogu pisania `\count()` zamiast `count()`
- Zmieniono `global_namespace_import.import_classes` z `false` na `true` — wymusza `use Exception;` i `use Throwable;` zamiast `\Exception` i `\Throwable` inline

**Cel:** czytelniejszy kod bez backslashy przy globalnych klasach; standardowy styl `count()` bez prefiksu.

### Fix: zmiana `composer update` na `composer install` w entrypoint.sh

`entrypoint.sh` wywoływał `composer update`, który przy każdym starcie kontenera rozwiązywał zależności od nowa (wolne). Zamieniono na `composer install`, który instaluje dokładne wersje z `composer.lock` — znacznie szybciej.

**Zmieniony plik:** `symfony-app/entrypoint.sh`

### Fix: przeniesienie `vendor/` do named volume (Docker bind mount issue na Windows)

Bind mount `./symfony-app:/app:cached` powodował, że `composer install` zapisywał tysiące plików przez most Windows↔Linux (Docker Desktop + WSL2) — bardzo wolne. Rozwiązanie: dodanie named volume `symfony-vendor` dla `/app/vendor/` i `symfony-composer-cache` dla cache composera, które żyją wyłącznie w filesystemie Linuksa wewnątrz Dockera.

**Zmieniony plik:** `docker-compose.yml`

### Fix: przeniesienie `var/` do named volume (wolny cache:clear na Windows)

`cache:clear` (i każde odświeżenie cache przez Symfony) operuje na tysiącach małych plików w `var/cache/`. Katalog ten leżał w bind-mountowanym `./symfony-app:/app:cached` — każda operacja plikowa przechodziła przez most Windows↔Linux (Docker Desktop + WSL2), co było bardzo wolne.

Rozwiązanie: dodanie named volume `symfony-var` dla `/app/var/`, analogicznie jak wcześniej zrobiono dla `vendor/`. Cały katalog `var/` (cache, logi, sessions) żyje teraz wyłącznie w filesystemie Linuksa wewnątrz Dockera.

**Zmieniony plik:** `docker-compose.yml`

### Fix: healthchecks dla baz danych + naprawa pustego stage `base` w Symfony Dockerfile

**docker-compose.yml:**
- Dodano `healthcheck` (`pg_isready -U postgres`, interval 5s, timeout 5s, retries 5) do `phoenix-db` i `symfony-db`
- `depends_on` zmieniony z listy na mapę z `condition: service_healthy` dla obu baz; `phoenix` w zależnościach `symfony` ma `condition: service_started`

**symfony-app/Dockerfile:**
- Przeniesiono `RUN apt-get install` i `COPY --from=composer` z `dev` i `prod` do stage `base` (eliminacja duplikacji)

---

### Zadanie 4: Rate-limiting w PhoenixApi (OTP GenServer)

Zaimplementowano rate-limiting na endpoincie `GET /api/photos` z użyciem OTP GenServer (sliding-window algorithm).

**Limity:**
- Per użytkownik: max **5 żądań na 10 minut**
- Globalny: max **1000 żądań na godzinę**

**Nowe pliki:**
- `phoenix-api/lib/phoenix_api/rate_limiter.ex` — GenServer zarządzający stanem limitów w pamięci; używa `System.monotonic_time(:millisecond)` do sliding window; co 5 minut samoczynnie usuwa stare wpisy (`Process.send_after/3`)
- `phoenix-api/lib/phoenix_api_web/plugs/rate_limit.ex` — Plug wywołujący `RateLimiter.check_and_record(user_id)`; w przypadku przekroczenia limitu zwraca HTTP 429

**Zmienione pliki:**
- `phoenix-api/lib/phoenix_api/application.ex` — dodano `PhoenixApi.RateLimiter` do drzewa supervisora (strategia `one_for_one`), co zapewnia automatyczny restart procesu GenServera
- `phoenix-api/lib/phoenix_api_web/controllers/photo_controller.ex` — dodano `plug PhoenixApiWeb.Plugs.RateLimit` po `Authenticate`

**Architektura OTP:**
- `RateLimiter` startuje jako nazwany proces (`name: __MODULE__`) nadzorowany przez główny supervisor aplikacji
- Stan: `%{config: %{...}, user_requests: %{user_id => [timestamps]}, global_requests: [timestamps]}`
- Limity przekazywane przez `start_link/1` opts (domyślne = produkcyjne) — pozwala startować izolowane procesy w testach z małymi limitami
- Każde `handle_call` filtruje stare timestampy, sprawdza limity i atomowo zapisuje nowe żądanie — brak race conditions dzięki jednowątkowej naturze GenServera
- Cleanup co 5 minut usuwa wpisy użytkowników nieaktywnych > 10 minut

**Testy (15 testów, 0 failures):**
- `test/phoenix_api/rate_limiter_test.exs` — 7 unit testów (async); startują izolowany GenServer z małymi limitami (`user_limit: 3, user_window_ms: 500, global_limit: 5, global_window_ms: 1000`); testują: przepuszczanie do limitu, blokowanie po przekroczeniu, niezależność między userami, odblokowanie po wygaśnięciu okna, priorytet limitu globalnego
- `test/phoenix_api_web/controllers/photo_controller_test.exs` — 2 integration testy HTTP: 429 po 6. żądaniu tego samego usera, niezależność limitów między userami

**Uruchamianie testów:**
```bash
docker compose exec -e DB_HOST=phoenix-db phoenix sh -c "MIX_ENV=test mix test"
```

---

## Fix: testy i PHPStan (2026-03-01)

### Fix: `PhotoRepository::countFiltered` — usunięcie ORDER BY z zapytania COUNT

`buildFilteredQuery()` zawierał `->orderBy('p.id', 'ASC')`. Gdy `countFiltered()` nadpisywało SELECT na `COUNT(p.id)`, PostgreSQL odrzucał zapytanie błędem: *column "p0_.id" must appear in the GROUP BY clause* — bo `ORDER BY p.id` bez GROUP BY jest niedozwolone przy agregacie.

**Fix:** dodano `->resetDQLPart('orderBy')` w `countFiltered()` przed `->getQuery()`.

**Skutek:** naprawia też wszystkie testy korzystające z `GET /` (HomeController, AuthController, PhotoController) — każde z nich wywołuje `HomeService::getPhotosData()` → `countFiltered()`.

### Fix: `ProfileControllerTest::testImportWithNoTokenShowsError` — zły użytkownik

Test sprawdzał zachowanie gdy user NIE MA tokenu Phoenix, ale używał `demo`, który MA `phoenixToken` ustawiony w fixtures (`test_token_user1_abc123`). Kontroler nie wyświetlał "Najpierw zapisz token", bo token istniał.

**Fix:** zmieniono `loginAs('demo')` → `loginAs('nature_lover')` — ten user nie ma tokenu Phoenix w fixtures.

### Fix: PHPStan — `HomeController` i `PhotoRepositoryTest`

- `HomeController.php:36` — `->get('page', 1)` — drugi parametr `InputBag::get()` musi być `string|null`; zmieniono domyślną wartość `1` → `'1'`
- `PhotoRepositoryTest.php:37` — `getLocation()` zwraca `string|null`; dodano `assertNotNull()` przed `assertStringContainsString()`

---

## Poprawki uzupełniające (2026-03-01)

### Fix: N+1 query w HomeService — batch query dla lajków

`HomeService::getPhotosData()` wykonywało jedno zapytanie SQL na każde zdjęcie (`hasUserLikedPhoto`) — przy N zdjęciach = N+1 zapytań łącznie.

**Zmiana:**
- `LikeRepositoryInterface` + `LikeRepository` — nowa metoda `getUserLikesForPhotoIds(User $user, array $photoIds): array<int, bool>`, która pobiera wszystkie polubienia użytkownika dla listy zdjęć jednym zapytaniem (`WHERE l.photo IN (:photoIds)`)
- `LikeRepository::hasUserLikedPhoto()` — zoptymalizowane: zamiast fetchowania `l.id` i `count()` używa teraz `COUNT(l.id)` + `getSingleScalarResult()`
- `HomeService` — zastąpiono pętlę wywołań `hasUserLikedPhoto` jednym wywołaniem `getUserLikesForPhotoIds`; zmieniono typ zależności z `LikeRepository` (klasa konkretna) na `LikeRepositoryInterface`
- `HomeServiceTest` — zaktualizowano mock do nowej metody batch; dodano test dla metadanych paginacji

### Refaktor: wydzielenie auth-guard w ProfileController

Wzorzec `getUserId() → null? → redirect; findUser() → null? → clear + redirect` był identycznie powielony w 3 metodach kontrolera.

**Zmiana:**
- Wydzielono prywatną metodę `requireAuthenticatedUser(Request): User|RedirectResponse`
- Każda z 3 metod (`profile`, `saveToken`, `import`) teraz wywołuje ją i sprawdza typ zwróconej wartości

### Dodanie paginacji na stronie głównej

`findAllWithUsersFiltered()` robiło `SELECT *` bez `LIMIT` — problem skalowalności przy dużej liczbie zdjęć.

**Zmiana:**
- `PhotoRepository::findAllWithUsersFiltered()` — dodano `int $page = 1, int $perPage = 12`, używa `setFirstResult/setMaxResults`; wydzielono prywatną metodę `buildFilteredQuery()` eliminując duplikację klauzul WHERE między `findAllWithUsersFiltered` i nowym `countFiltered`
- `PhotoRepository::countFiltered()` — nowa metoda zliczająca wyniki z tymi samymi filtrami
- `HomeService::getPhotosData()` — dodano `int $page = 1`, zwraca `currentPage` i `totalPages`
- `HomeController` — czyta `?page=` z query string (`max(1, ...)` dla ochrony przed ujemnymi wartościami)
- `templates/home/index.html.twig` — nawigacja Poprzednia/Następna zachowująca aktywne filtry

### Fix: walidacja struktury odpowiedzi PhoenixClient

`$response->toArray()['photos'] ?? []` cicho zwracało pustą tablicę gdy API zmieniło kontrakt — błąd był niewidoczny.

**Zmiana:**
- Zamiast `?? []` sprawdzamy `array_key_exists('photos', $data) && is_array($data['photos'])` i rzucamy `\RuntimeException` przy niezgodności struktury

### Fix: timezone UTC w filtrze taken_at (PhotoRepository)

`new DateTimeImmutable($filters['taken_at'])` bez podanej strefy czasowej używało strefy serwera, co mogło powodować off-by-one przy filtrze daty.

**Zmiana:** `new DateTimeImmutable($filters['taken_at'], new DateTimeZone('UTC'))`

### Fix: spójność tokenów Phoenix w fixture Symfony

Demo user w `AppFixtures` nie miał ustawionego `phoenixToken` — integracja importu nie działała od razu po uruchomieniu projektu.

**Zmiana:** dodano `phoenixToken: 'test_token_user1_abc123'` do danych demo usera (wartość zgodna z `phoenix-api/priv/repo/seeds.exs`)

### Dodanie testów filtrowania PhotoRepository

Nowy plik: `tests/Functional/PhotoRepositoryTest.php` — 9 testów pokrywających filtrowanie i paginację:
- Filtrowanie po `location`, `camera` (case-insensitive), `username`, `taken_at`
- Ignorowanie błędnego formatu daty
- Paginacja (dwie strony po 6, brak powtórzeń)
- `countFiltered` z i bez filtrów

### Phoenix: Retry-After header przy rate limitingu (RFC 6585)

HTTP 429 bez `Retry-After` headera to niepełna implementacja RFC — klient nie wie kiedy ponowić żądanie.

**Zmiana:**
- `RateLimiter::check_and_record/2` — przy przekroczeniu limitu zwraca teraz `{:error, :user_limit | :global_limit, remaining_ms}` gdzie `remaining_ms` to czas do wygaśnięcia najstarszego żądania w oknie
- `RateLimit` Plug — odczytuje `remaining_ms` i ustawia `Retry-After: <sekundy>` (ceiling z ms → s)
- Testy zaktualizowane: asercje pattern match `{:error, :user_limit, remaining}` zamiast == porównania; nowy test integration sprawdzający nagłówek `Retry-After`

### Architektura: GenServer vs ETS — uzasadnienie wyboru

`RateLimiter` używa pojedynczego procesu GenServer zamiast ETS z atomic operations. **Trade-off:**

- **GenServer**: proste w implementacji, brak race conditions (jednowątkowy model aktora), łatwy do testowania z izolowanymi instancjami. **Wada**: przy dużym ruchu (`check_and_record` synchroniczne) mailbox rośnie → latency → timeout.
- **ETS `:public` + `update_counter`**: współbieżne odczyty/zapisy bez serialization, brak bottlenecku procesu. **Wada**: trudniejsza implementacja sliding-window (ETS nie ma TTL), wymaga osobnego cleaner procesu lub persistent ETS dla przeżycia restartu.

**Decyzja**: GenServer jest właściwy dla tego use case — import zdjęć to rzadka operacja (max 5 × liczba userów na 10 min), ruch nie uzasadnia złożoności ETS. W systemie produkcyjnym z dużym ruchem użyłbym ETS lub Redis (Redix).

**State loss przy restarcie**: supervisor restartuje GenServer po awarii, ale timestamps są tracone — limity się zerują. Akceptowalne dla rate limitingu importu; produkcyjnie: ETS `:public` (poza GenServerem) lub Redis.

