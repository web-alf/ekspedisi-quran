<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Field :attribute harus diterima.',
    'accepted_if' => 'Field :attribute harus diterima bila :other adalah :value.',
    'active_url' => 'Field :attribute bukan URL yang sah.',
    'after' => 'Field :attribute harus tanggal setelah :date.',
    'after_or_equal' => 'Field :attribute harus tanggal setelah atau sama dengan :date.',
    'alpha' => 'Field :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Field :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Field :attribute hanya boleh berisi huruf dan angka.',
    'array' => 'Field :attribute harus berupa array.',
    'ascii' => 'Field :attribute hanya boleh berisi karakter alfanumerik byte tunggal dan simbol.',
    'before' => 'Field :attribute harus tanggal sebelum :date.',
    'before_or_equal' => 'Field :attribute harus tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Field :attribute harus memiliki antara :min dan :max item.',
        'file' => 'Field :attribute harus antara :min dan :max kilobytes.',
        'numeric' => 'Field :attribute harus antara :min dan :max.',
        'string' => 'Field :attribute harus antara :min dan :max karakter.',
    ],
    'boolean' => 'Field :attribute harus bernilai benar atau salah.',
    'can' => 'Field :attribute berisi nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi field :attribute tidak cocok.',
    'contains' => 'Field :attribute tidak mengandung nilai yang diperlukan.',
    'current_password' => 'Password salah.',
    'date' => 'Field :attribute bukan tanggal yang sah.',
    'date_equals' => 'Field :attribute harus tanggal yang sama dengan :date.',
    'date_format' => 'Field :attribute tidak cocok dengan format :format.',
    'decimal' => 'Field :attribute harus memiliki :decimal tempat desimal.',
    'declined' => 'Field :attribute harus ditolak.',
    'declined_if' => 'Field :attribute harus ditolak bila :other adalah :value.',
    'different' => 'Field :attribute dan :other harus berbeda.',
    'digits' => 'Field :attribute harus :digits digit.',
    'digits_between' => 'Field :attribute harus antara :min dan :max digit.',
    'dimensions' => 'Field :attribute memiliki dimensi gambar yang tidak sah.',
    'distinct' => 'Field :attribute memiliki nilai duplikat.',
    'doesnt_end_with' => 'Field :attribute tidak boleh diakhiri dengan salah satu dari berikut: :values.',
    'doesnt_start_with' => 'Field :attribute tidak boleh dimulai dengan salah satu dari berikut: :values.',
    'email' => 'Field :attribute harus alamat email yang sah.',
    'ends_with' => 'Field :attribute harus diakhiri dengan salah satu dari berikut: :values.',
    'enum' => 'Yang dipilih :attribute tidak sah.',
    'exists' => 'Yang dipilih :attribute tidak sah.',
    'extensions' => 'Field :attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => 'Field :attribute harus berupa file.',
    'filled' => 'Field :attribute harus memiliki nilai.',
    'gt' => [
        'array' => 'Field :attribute harus memiliki lebih dari :value item.',
        'file' => 'Field :attribute harus lebih besar dari :value kilobytes.',
        'numeric' => 'Field :attribute harus lebih besar dari :value.',
        'string' => 'Field :attribute harus lebih besar dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Field :attribute harus memiliki :value item atau lebih.',
        'file' => 'Field :attribute harus lebih besar atau sama dengan :value kilobytes.',
        'numeric' => 'Field :attribute harus lebih besar atau sama dengan :value.',
        'string' => 'Field :attribute harus lebih besar atau sama dengan :value karakter.',
    ],
    'hex_color' => 'Field :attribute harus warna heksadesimal yang sah.',
    'image' => 'Field :attribute harus berupa gambar.',
    'in' => 'Yang dipilih :attribute tidak sah.',
    'in_array' => 'Field :attribute harus ada di :other.',
    'integer' => 'Field :attribute harus berupa bilangan bulat.',
    'ip' => 'Field :attribute harus alamat IP yang sah.',
    'ipv4' => 'Field :attribute harus alamat IPv4 yang sah.',
    'ipv6' => 'Field :attribute harus alamat IPv6 yang sah.',
    'json' => 'Field :attribute harus string JSON yang sah.',
    'list' => 'Field :attribute harus berupa list.',
    'lowercase' => 'Field :attribute harus huruf kecil.',
    'lt' => [
        'array' => 'Field :attribute harus memiliki kurang dari :value item.',
        'file' => 'Field :attribute harus kurang dari :value kilobytes.',
        'numeric' => 'Field :attribute harus kurang dari :value.',
        'string' => 'Field :attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Field :attribute tidak boleh memiliki lebih dari :value item.',
        'file' => 'Field :attribute harus kurang dari atau sama dengan :value kilobytes.',
        'numeric' => 'Field :attribute harus kurang dari atau sama dengan :value.',
        'string' => 'Field :attribute harus kurang dari atau sama dengan :value karakter.',
    ],
    'mac_address' => 'Field :attribute harus alamat MAC yang sah.',
    'max' => [
        'array' => 'Field :attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Field :attribute tidak boleh lebih besar dari :max kilobytes.',
        'numeric' => 'Field :attribute tidak boleh lebih besar dari :max.',
        'string' => 'Field :attribute tidak boleh lebih besar dari :max karakter.',
    ],
    'max_digits' => 'Field :attribute tidak boleh memiliki lebih dari :max digit.',
    'mimes' => 'Field :attribute harus file bertipe: :values.',
    'mimetypes' => 'Field :attribute harus file bertipe: :values.',
    'min' => [
        'array' => 'Field :attribute harus memiliki setidaknya :min item.',
        'file' => 'Field :attribute harus setidaknya :min kilobytes.',
        'numeric' => 'Field :attribute harus setidaknya :min.',
        'string' => 'Field :attribute harus setidaknya :min karakter.',
    ],
    'min_digits' => 'Field :attribute harus memiliki setidaknya :min digit.',
    'missing' => 'Field :attribute harus hilang.',
    'missing_if' => 'Field :attribute harus hilang bila :other adalah :value.',
    'missing_unless' => 'Field :attribute harus hilang kecuali :other adalah :value.',
    'missing_with' => 'Field :attribute harus hilang bila :values ada.',
    'missing_with_all' => 'Field :attribute harus hilang bila :values ada.',
    'multiple_of' => 'Field :attribute harus kelipatan dari :value.',
    'not_in' => 'Yang dipilih :attribute tidak sah.',
    'not_regex' => 'Format field :attribute tidak sah.',
    'numeric' => 'Field :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Field :attribute harus mengandung setidaknya satu huruf.',
        'mixed' => 'Field :attribute harus mengandung setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Field :attribute harus mengandung setidaknya satu angka.',
        'symbols' => 'Field :attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => 'Field :attribute yang diberikan telah muncul dalam kebocoran data. Silakan pilih :attribute yang berbeda.',
    ],
    'present' => 'Field :attribute harus ada.',
    'present_if' => 'Field :attribute harus ada bila :other adalah :value.',
    'present_unless' => 'Field :attribute harus ada kecuali :other adalah :value.',
    'present_with' => 'Field :attribute harus ada bila :values ada.',
    'present_with_all' => 'Field :attribute harus ada bila :values ada.',
    'prohibited' => 'Field :attribute dilarang.',
    'prohibited_if' => 'Field :attribute dilarang bila :other adalah :value.',
    'prohibited_unless' => 'Field :attribute dilarang kecuali :other ada di :values.',
    'prohibits' => 'Field :attribute melarang :other untuk ada.',
    'regex' => 'Format field :attribute tidak sah.',
    'required' => 'Field :attribute wajib diisi.',
    'required_array_keys' => 'Field :attribute harus berisi entri untuk: :values.',
    'required_if' => 'Field :attribute wajib diisi bila :other adalah :value.',
    'required_if_accepted' => 'Field :attribute wajib diisi bila :other diterima.',
    'required_if_declined' => 'Field :attribute wajib diisi bila :other ditolak.',
    'required_unless' => 'Field :attribute wajib diisi kecuali :other ada di :values.',
    'required_with' => 'Field :attribute wajib diisi bila :values ada.',
    'required_with_all' => 'Field :attribute wajib diisi bila :values ada.',
    'required_without' => 'Field :attribute wajib diisi bila :values tidak ada.',
    'required_without_all' => 'Field :attribute wajib diisi bila tidak ada satupun dari :values yang ada.',
    'same' => 'Field :attribute harus cocok dengan :other.',
    'size' => [
        'array' => 'Field :attribute harus berisi :size item.',
        'file' => 'Field :attribute harus :size kilobytes.',
        'numeric' => 'Field :attribute harus :size.',
        'string' => 'Field :attribute harus :size karakter.',
    ],
    'starts_with' => 'Field :attribute harus dimulai dengan salah satu dari berikut: :values.',
    'string' => 'Field :attribute harus berupa string.',
    'timezone' => 'Field :attribute harus zona waktu yang sah.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => 'Field :attribute harus huruf besar.',
    'url' => 'Field :attribute harus URL yang sah.',
    'ulid' => 'Field :attribute harus ULID yang sah.',
    'uuid' => 'Field :attribute harus UUID yang sah.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "rule.attribute" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [
        'name' => 'nama',
        'location' => 'lokasi',
        'quote' => 'testimonial',
        'image' => 'gambar',
        'is_active' => 'status aktif',
        'email' => 'alamat email',
        'password' => 'kata sandi',
        'password_confirmation' => 'konfirmasi kata sandi',
        'phone' => 'nomor telepon',
        'address' => 'alamat',
        'title' => 'judul',
        'description' => 'deskripsi',
        'content' => 'konten',
    ],

];