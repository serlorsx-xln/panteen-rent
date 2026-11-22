<?php
/**
 * Panteen Rent: configuration
 *
 * Copy this file to config/config.php and edit the values.
 * The default values work with XAMPP on Windows: MySQL user "root", no password.
 */
return [
    'app' => [
        'name'     => 'Panteen Rent',
        // 'dev' opens the simulated mailbox (/dev/mailbox) and detailed errors to this computer only;
        // use 'production' when deployed (admins can still open the mailbox).
        'env'      => 'dev',
        // Address used in e-mailed links, e.g. https://rent.example.com. Leave empty on your own computer:
        // links then use the address you browse with, but only localhost / 127.0.0.1 or a host listed below.
        // Set it (or list the host) for any other address, such as a phone testing over Wi-Fi on 192.168.1.20:8080.
        'url'      => '',
        'hosts'    => [],
        'timezone' => 'Asia/Bangkok',
        // Random secret used for signing tokens. Change it on a real deployment.
        'key'      => 'change-me-to-a-long-random-string',
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'panteen_rent',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],

    'mail' => [
        // 'log'  = store every email in the simulated mailbox only (no internet needed: good for demos)
        // 'smtp' = really send via SMTP (also keeps a copy in the mailbox)
        'driver'     => 'log',
        'from_email' => 'no-reply@panteenrent.local',
        'from_name'  => 'Panteen Rent',
        'smtp' => [
            // Example for Gmail: host smtp.gmail.com, port 587, encryption tls,
            // username = your Gmail address, password = a 16-character App Password.
            'host'       => 'smtp.gmail.com',
            'port'       => 587,
            'encryption' => 'tls', // 'tls' (STARTTLS), 'ssl' or ''
            'username'   => '',
            'password'   => '',
            'timeout'    => 15,
        ],
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024, // 5 MB per image
    ],
];
