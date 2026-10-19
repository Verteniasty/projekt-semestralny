<?php

function wyslij_mail(string $do, string $temat, string $tresc): bool
{
    $tryb = $GLOBALS['config']['mail_tryb'] ?? 'plik';

    if ($tryb === 'mail') {
        $od = $GLOBALS['config']['mail_od'] ?? 'no-reply@localhost';
        $do = str_replace(["\r", "\n"], '', $do);
        $naglowki = 'From: ' . str_replace(["\r", "\n"], '', $od) . "\r\n"
                  . "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($do, '=?UTF-8?B?' . base64_encode($temat) . '?=', $tresc, $naglowki);
    }

    $katalog = __DIR__ . '/../storage';
    if (!is_dir($katalog)) {
        mkdir($katalog, 0775, true);
    }
    $wpis = '[' . date('Y-m-d H:i:s') . "]\nDO: {$do}\nTEMAT: {$temat}\n\n{$tresc}\n" . str_repeat('-', 60) . "\n";
    return file_put_contents($katalog . '/maile.log', $wpis, FILE_APPEND | LOCK_EX) !== false;
}
