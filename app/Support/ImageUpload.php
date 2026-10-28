<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The rules of an uploaded image: the photo of a person, the logo of an organization.
 */
final class ImageUpload
{
    /** An image up to 10 MB, HEIC included, as phones take them. */
    public const array RULES = ['file', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240'];
}
