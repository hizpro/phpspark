<?php

declare(strict_types=1);

namespace Hizpro\PhpSpark\Interfaces;

use Exception;
use Hizpro\PhpSpark\Entities\UploadedFile;

interface UploadFileValidatorInterface
{
    /**
     * Validate the uploaded file.
     *
     * @param UploadedFile $file
     * @return void
     * @throws Exception
     */
    public function validate(UploadedFile $file): void;
}
