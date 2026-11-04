DOWNLOAD INSTRUCTIONS FOR LOCAL ASSETS:
========================================

1. BOOTSTRAP 5.3.0 (CSS & JS):
   - Go to: https://github.com/twbs/bootstrap/releases/tag/v5.3.0
- Download: bootstrap-5.3.0-dist.zip
- Extract to: C:\xampp\htdocs\order-billing-system\public\
- Should result in:
     - public/css/bootstrap.min.css
     - public/js/bootstrap.bundle.min.js

2. BOOTSTRAP ICONS 1.10.0:
- Go to: https://github.com/twbs/icons/releases/tag/v1.10.0
- Download: bootstrap-icons-1.10.0.zip
- Extract to: C:\xampp\htdocs\order-billing-system\public\
- Should result in:
     - public/css/bootstrap-icons.min.css
     - public/fonts/bootstrap-icons/...

If fonts folder is missing, copy it from:
   bootstrap-icons-1.10.0/font/fonts/* → public/fonts/

After setup, your public folder should look like:
public/
├── css/
│   ├── bootstrap.min.css
│   ├── bootstrap-icons.min.css
│   └── style.css
├── js/
│   └── bootstrap.bundle.min.js
└── fonts/
    └── bootstrap-icons/
        ├── bootstrap-icons.woff
        └── bootstrap-icons.woff2
3. SWEETALERT2 11.x (CSS & JS) - bundled, works offline:
   - Already included:
        - public/css/sweetalert2.min.css
        - public/js/sweetalert2.min.js
   - Official source if you need to update:
        https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/
