# Live Runtime QA Report

## Environment
- App: `http://localhost:8080`
- Runtime: Docker (`web` + `db`)
- DB: MariaDB 10.6
- Media binaries: ffmpeg/ffprobe detected and available in container.

## QA Activities Executed
1. Applied runtime compatibility migration and verified schema columns.
2. Performed live browser module traversal across admin modules.
3. Fixed runtime failures discovered during traversal.
4. Re-ran live browser traversal to confirm stabilization.
5. Ran media upload + queue processing validation with authenticated browser session.

## Final Runtime Status
- Core admin modules: PASS (HTTP 200, no unhandled exceptions in tested scope).
- Permission-gated modules: 403 for current logged-in admin (`business-settings.php`, `admin_users.php`, `change-password.php`).
- Download endpoint: behaves as file download flow (not a rendered document).

## Media QA Status
- Upload acceptance test:
  - MOV, AVI, MKV, MPEG, MP4, WEBM accepted (HTTP 201) when CSRF token included.
- Background processing:
  - Queue jobs created for media transcode.
  - Valid recorder-generated webm fixture completed full chain:
    - `media_transcode` completed
    - `media_thumbnail_generate` completed
    - `media_cleanup` completed
  - Media asset updated with `conversion_status=ready`, `transcoding_status=optimized`, poster thumbnail path.

## Known Non-Blocking Items
- Historical synthetic fixture jobs remain in retry/backoff state with expected conversion errors.
- Image optimization queue entries show historical `imagewebp/cwebp` dependency errors in existing records.
- Non-media queued jobs (e.g., communication mapping) can consume worker budget unless filtered/prioritized.
