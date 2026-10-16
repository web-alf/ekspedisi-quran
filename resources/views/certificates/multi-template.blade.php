<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sertifikat Wakaf Al-Qur'an</title>
    <style>
        @page {
            size: {{ $template->width }}px {{ $template->height }}px landscape;
            margin: 0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .page {
            width: {{ $template->width }}px;
            height: {{ $template->height }}px;
            position: relative;
            page-break-after: always;
            page-break-inside: avoid;
            overflow: hidden;
        }
        
        .page:last-child {
            page-break-after: auto;
        }
        
        .certificate-bg {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .certificate-bg img {
            width: 100%;
            height: 100%;
            object-fit: fill;
        }
        
        .certificate-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
        }
        
        .field-text {
            position: absolute;
            font-family: 'Times New Roman', Times, serif !important;
            text-align: center;
            white-space: nowrap;
            overflow: visible;
            transform-origin: center center;
            transform: translate(-50%, -50%);
        }

        /* Additional styling for specific fields */
        .field-wakif_name {
            font-weight: bold;
            text-transform: uppercase;
        }

        .field-mushaf_count {
            font-style: italic;
        }

        .field-tanggal_wakaf {
            letter-spacing: 1px;
        }

        .field-donation_number {
            font-weight: 600;
        }
    </style>
</head>
<body>
    @foreach($pages as $index => $data)
    <div class="page">
        <div class="certificate-bg">
            <img src="{{ $template_base64 }}" alt="Certificate Background">
        </div>
        
        <div class="certificate-content">
            @foreach($field_positions as $field => $position)
                @if(isset($data[$field]))
                    <div class="field-text field-{{ $field }}" style="
                        left: {{ $position['x'] }}px;
                        top: {{ $position['y'] }}px;
                        font-size: {{ $position['font_size'] ?? 24 }}px;
                        color: {{ $position['color'] ?? '#000000' }};
                        width: {{ $position['width'] ?? 'auto' }}px;
                        @if(isset($position['rotation']) && $position['rotation'] != 0)
                            transform: rotate({{ $position['rotation'] }}deg);
                        @endif
                    ">
                        {{ $data[$field] }}
                    </div>
                @endif
            @endforeach
        </div>
    </div>
    @endforeach
</body>
</html>