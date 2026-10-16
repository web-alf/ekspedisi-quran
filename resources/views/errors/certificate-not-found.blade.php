<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Tidak Ditemukan - Ekspedisi Quran</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f7fafc;
            color: #2d3748;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 500px;
        }
        h1 {
            font-size: 6rem;
            margin: 0;
            color: #e53e3e;
            font-weight: 300;
        }
        h2 {
            font-size: 1.5rem;
            margin: 1rem 0;
            color: #2d3748;
        }
        p {
            color: #718096;
            margin: 1rem 0;
            line-height: 1.6;
        }
        .button {
            display: inline-block;
            background-color: #4299e1;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.375rem;
            text-decoration: none;
            margin-top: 1.5rem;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #3182ce;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📄❌</div>
        <h1>404</h1>
        <h2>Sertifikat Tidak Ditemukan</h2>
        <p>
            @if(isset($message) && strpos($message, 'expired') !== false)
                Link sertifikat ini telah kedaluwarsa. Silakan hubungi admin untuk mendapatkan link baru.
            @elseif(isset($message) && strpos($message, 'Invalid') !== false)
                Link sertifikat tidak valid. Pastikan Anda menggunakan link yang benar.
            @else
                Maaf, sertifikat yang Anda cari tidak dapat ditemukan atau link sudah tidak berlaku.
            @endif
        </p>
        <a href="/" class="button">Kembali ke Beranda</a>
    </div>
</body>
</html>