<?php

declare(strict_types=1);

/*
 * The copy from Directus (`directus:copy`): where the files of Directus are.
 * The database is the `directus` connection of config/database.php.
 */
return [
    /* The `uploads` folder of Directus, mounted read only by compose.yaml. */
    'uploads' => env('DIRECTUS_UPLOADS', '/directus-uploads'),
];
