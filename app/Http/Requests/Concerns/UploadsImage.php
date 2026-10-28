<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * A form that sends an image with its fields, like the photo of a person.
 */
trait UploadsImage
{
    /**
     * The image sent in a field, if there is one.
     *
     * @param  string  $field  the name of the field
     */
    public function uploadedImage(string $field): ?UploadedFile
    {
        $image = $this->file($field);

        return $image instanceof UploadedFile ? $image : null;
    }
}
