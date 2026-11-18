# FB Scheduler (VPS)

MVP panelu harmonogramu postów do grup Facebooka. **To nie jest autopublikator**: o ustalonej godzinie post przechodzi ze statusu `scheduled` do `ready`, a publikację wykonujesz ręcznie na Facebooku. Dzięki temu aplikacja nie przechowuje sesji FB i nie próbuje omijać zabezpieczeń platformy.

## Instalacja (Ubuntu VPS + Docker)

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin
sudo systemctl enable --now docker
cp .env.example .env
nano .env  # ustaw mocne ADMIN_PASSWORD
sudo docker compose up -d --build
```

Panel nasłuchuje wyłącznie na 127.0.0.1:8000 serwera. Z komputera Windows otwórz tunel SSH:

```powershell
ssh -L 8000:127.0.0.1:8000 root@ADRES_VPS
```

Następnie w przeglądarce otwórz http://127.0.0.1:8000 i zaloguj się danymi z `.env`.

## Obsługa

Dodaj link grupy, treść i termin według czasu polskiego. Po nadejściu terminu status zmieni się na `ready`. Otwórz grupę, wklej treść i opublikuj zgodnie z zasadami grupy. Potem kliknij „Oznacz jako opublikowany”.

## Ograniczenia i dalsze kroki

- Aktualna wersja obsługuje tekst i jeden termin na wpis, bez obrazów i cykliczności.
- Nie wysyła powiadomień. Aby sprawdzić gotowe posty, otwórz panel.
- Używaj jednego procesu Uvicorn (harmonogram działa wewnątrz aplikacji).
- Nie wystawiaj portu 8000 publicznie bez HTTPS, reverse proxy i dodatkowych zabezpieczeń.
