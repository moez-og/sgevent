# Face Login Service

## 1) Create Python environment

```powershell
cd faceid_service
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

## 2) Run service

```powershell
python app.py
```

The API starts on `http://127.0.0.1:5501`.

## 3) Symfony integration

Symfony sends requests to `/compare-face` using `FACE_AUTH_API_URL` from `.env.local`.

Expected payload:

```json
{
  "email": "user@example.com",
  "image": "data:image/jpeg;base64,..."
}
```

The service compares the live camera image against the user's stored profile image (`imageUrl`).
