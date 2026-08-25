<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikat Wakaf</title>
    <style>
        @page {
            margin: 0;
            size: {{ $template->width }}px {{ $template->height }}px;
        }
        body {
            margin: 0;
            padding: 0;
            /* FONT STANDARDISASI: Times New Roman untuk consistency */
            font-family: 'Times New Roman', Times, serif;
        }
        .certificate-container {
            position: relative;
            width: {{ $template->width }}px;
            height: {{ $template->height }}px;
        }
        .background-image {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: -1;
        }
        .field {
            position: absolute;
            white-space: nowrap;
            font-weight: bold;
            font-family: 'Times New Roman', Times, serif;
            transform: translate(-50%, -50%);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <img src="{{ $template_base64 }}" class="background-image" alt="Template Sertifikat">

        @foreach($field_positions as $fieldName => $position)
            @if(isset($data[$fieldName]))
                <div class="field" style="
                    top: {{ $position['y'] }}px;
                    left: {{ $position['x'] }}px;
                    font-size: {{ $position['font_size'] ?? 24 }}px;
                    color: {{ $position['color'] ?? '#000000' }};
                    font-family: 'Times New Roman', Times, serif;
                    font-weight: bold;
                ">
                    {{ $data[$fieldName] }}
                </div>
            @endif
        @endforeach
    </div>
</body>
</html>
