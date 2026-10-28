<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * A form that sends files of documents in `files`: PDF, JPG, PNG or HEIC, at
 * most 25 MB each and 20 at a time. The type is the real one, read from the
 * content, not from the name.
 */
trait UploadsFiles
{
    /** The rule of one file of a document, here and when its file is changed. */
    public const array FILE = ['required', 'file', 'mimes:pdf,jpg,jpeg,png,heic', 'max:25600'];

    /**
     * The rules of the files.
     *
     * @param  bool  $required  at least one file must be sent
     * @return array<string, list<string>>
     */
    protected function filesRules(bool $required): array
    {
        return [
            'files' => [$required ? 'required' : 'nullable', 'array', 'max:20'],
            'files.*' => self::FILE,
        ];
    }

    /**
     * The messages of the files that say what to do, instead of the generic ones.
     *
     * @return array<string, string>
     */
    protected function filesMessages(): array
    {
        $tooBig = __('Each file can be at most 25 MB.');

        return [
            'files.required' => __('Choose at least one file.'),
            'files.max' => __('Upload at most 20 files at a time.'),
            'files.*.mimes' => __('Only PDF, JPG, PNG and HEIC files can be uploaded.'),
            'files.*.max' => $tooBig,
            'files.*.uploaded' => $tooBig,
        ];
    }

    /**
     * The files sent, none when the form sent none.
     *
     * @return list<UploadedFile>
     */
    public function uploadedFiles(): array
    {
        $files = $this->file('files');

        return is_array($files) ? array_values($files) : [];
    }
}
