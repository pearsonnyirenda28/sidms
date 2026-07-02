# SIDMS — Secure Intelligent Document Management System v2.0

**Developed by Bl@de_zar**

A self‑hosted, enterprise‑grade document management platform with strong encryption, two‑factor authentication, AI‑powered insights, and real‑time security monitoring.

---

## Stack
- **Backend**: PHP 8.x + MySQL 8.x
- **Frontend**: Bootstrap 5, Chart.js, vanilla JavaScript
- **Server**: Apache (XAMPP, LAMP, LEMP)

---

## Features

| Category | Feature |
|----------|---------|
| **Encryption** | AES‑256‑CBC per‑file key, wrapped with a 32‑char master key |
| **Authentication** | Two‑factor via TOTP (Google Authenticator compatible) with QR code setup |
| **Password Policy** | Minimum 8 characters, must include uppercase, lowercase, digit, and special symbol |
| **Role‑Based Access** | Admin / User separation; admin‑only download, delete, and full file visibility |
| **View‑Only Preview** | Watermarked viewer with disabled printing, right‑click, keyboard shortcuts |
| **Device Limits** | User = 1 concurrent session, Admin = 2 concurrent sessions |
| **Admin Account Limit** | Maximum 7 admin accounts enforced |
| **File Type Restriction** | Only images, videos, PDFs, Office documents, and plain text accepted |
| **AI Document Assistant** | Summarisation, key concept extraction, overview, and interactive Q&A |
| **AI Caching** | Similarity‑based caching to save API tokens and speed up responses |
| **Temporary Share Links** | Time‑limited (1 hour) public view links with QR code; no login required |
| **Anomaly Detection** | Brute‑force protection and excessive download alerts with optional SMS |
| **Real‑time Admin Dashboard** | Live stats (users, files, storage, alerts) refreshed every 5 seconds |
| **Audit Logging** | Every action logged with user ID, IP address, and timestamp |
| **PWA Support** | Installable on mobile/home screen with a manifest and service worker |
| **Toast Notifications** | WhatsApp‑style non‑blocking feedback replacing all native popups |
| **Security Headers** | X‑Frame‑Options, X‑Content‑Type‑Options, Referrer‑Policy, Permissions‑Policy |
| **Developer Attribution** | Visible footer and meta tags for legal ownership |
