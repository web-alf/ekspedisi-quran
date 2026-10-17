<?php

namespace App\Exceptions;

/**
 * Penolakan serah terima kurir yang wajar (bukan galat sistem): pesannya untuk
 * ditampilkan apa adanya ke kurir, dan transaksinya dibatalkan.
 */
class SerahTerimaDitolak extends \RuntimeException {}
