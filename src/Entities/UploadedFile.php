<?php

declare(strict_types=1);

namespace Hizpro\PhpSpark\Entities;

use http\Exception\InvalidArgumentException;

class UploadedFile
{
    public string $name;
    public string $type;
    public string $tmp_name;
    public int $error;
    public int $size;

    public function __construct(string $name, string $type, string $tmp_name, int $error, int $size)
    {
        $this->name = $name;
        $this->type = $type;
        $this->tmp_name = $tmp_name;
        $this->error = $error;
        $this->size = $size;
    }

    /**
     * @param string $fileInputName
     * @return self
     */
    public static function createFile(string $fileInputName): self
    {
        $fileInfo = $_FILES[$fileInputName];
        return new self(
            $fileInfo['name'],
            $fileInfo['type'],
            $fileInfo['tmp_name'],
            $fileInfo['error'],
            $fileInfo['size']
        );
    }

    /**
     * @param string $fileInputName
     * @return array<int|string, self>
     */
    public static function createFiles(string $fileInputName): array
    {
        $filesInfo = $_FILES[$fileInputName];

        if (!is_array($filesInfo['name'])) {
            $message = sprintf("The input '%s' does not contain multiple files.", $fileInputName);
            throw new InvalidArgumentException($message);
        }

        $files = [];
        foreach ($filesInfo['name'] as $index => $name) {
            $files[$index] = new self(
                $name,
                $filesInfo['type'][$index],
                $filesInfo['tmp_name'][$index],
                $filesInfo['error'][$index],
                $filesInfo['size'][$index],
            );
        }
        return $files;
    }
}
