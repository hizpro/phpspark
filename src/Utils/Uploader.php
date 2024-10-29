<?php

declare(strict_types=1);

namespace Hizpro\PhpSpark\Utils;

use Exception;
use Hizpro\PhpSpark\Entities\UploadedFile;
use Hizpro\PhpSpark\Interfaces\UploadFileValidatorInterface;

/**
 * Uploader: upload a file or multiple files
 */
class Uploader
{
    /**
     * @var UploadedFile
     */
    protected UploadedFile $file;
    /**
     * @var string
     */
    protected string $destPath;

    /**
     * Uploader constructor
     *
     * @param UploadedFile $file
     * @param string $uploadPath
     * @param string $dirname
     * @throws Exception
     */
    public function __construct(UploadedFile $file, string $uploadPath, string $dirname = '')
    {
        try {
            if ($file->error !== UPLOAD_ERR_OK) {
                self::handleUploadError($file->error);
            }
            $this->file = $file;
            $this->destPath = self::resolveDestPath($uploadPath, $dirname);
        } catch (Exception $e) {
            $message = sprintf('%s upload failed: %s', $file->name, $e->getMessage());
            throw new Exception($message);
        }
    }

    /**
     * Handle upload error based on the error code
     *
     * @param int $error
     * @return void
     * @throws Exception
     */
    private static function handleUploadError(int $error): void
    {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.',
        };

        throw new Exception($message);
    }

    /**
     * Resolve the destination path for the uploaded file
     *
     * @param string $uploadPath
     * @param string $dirname
     * @return string
     * @throws Exception
     */
    private static function resolveDestPath(string $uploadPath, string $dirname): string
    {
        $documentRoot = self::getDocumentRootPath();
        $destPath = rtrim($uploadPath, '/');

        if ($destPath[0] !== '/') {
            $destPath = $documentRoot . DIRECTORY_SEPARATOR . $destPath;
        }

        if ($dirname !== '') {
            $destPath .= DIRECTORY_SEPARATOR . trim($dirname, '/');
        }

        return $destPath;
    }

    /**
     * Upload a single file and return its path
     *
     * @param UploadFileValidatorInterface|null $validator
     * @param callable|null $filenameCallback
     * @return string
     * @throws Exception
     */
    public function uploadFile(
        ?UploadFileValidatorInterface $validator = null,
        ?callable $filenameCallback = null
    ): string {
        try {
            $validator?->validate($this->file);

            if (!is_dir($this->destPath)) {
                mkdir($this->destPath, 0755, true);
            }

            $destPath = realpath($this->destPath);
            if ($destPath === false) {
                throw new Exception('Invalid dest path');
            }

            $documentRoot = self::getDocumentRootPath();
            if (!str_starts_with($destPath, $documentRoot)) {
                throw new Exception('The upload path exceeds the access range.');
            }

            if ($filenameCallback && is_callable($filenameCallback)) {
                $destFilename = $filenameCallback($this->file->name);
            } else {
                $destFilename = self::generateFilename($this->file->name);
            }
            self::validateDestFilename($destFilename);

            $destFile = $destPath . DIRECTORY_SEPARATOR . $destFilename;

            $count = 1;
            $fileInfo = pathinfo($destFile);
            while (file_exists($destFile)) {
                $newFilename = sprintf('%s_%d', $fileInfo['filename'], $count);
                if (!empty($fileInfo['extension'])) {
                    $newFilename .= sprintf('.%s', $fileInfo['extension']);
                }
                $destFile = $destPath . DIRECTORY_SEPARATOR . $newFilename;
                $count++;
            }

            if (false === move_uploaded_file($this->file->tmp_name, $destFile)) {
                throw new Exception('Cannot write file to disk.');
            }

            $urlFile = str_replace($documentRoot, '', $destFile);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!isset($_SESSION['uploaded_files'])) {
                $_SESSION['uploaded_files'] = [];
            }
            $_SESSION['uploaded_files'][$urlFile] = $destFile;

            return $urlFile;
        } catch (Exception $e) {
            $message = sprintf('%s upload failed: %s', $this->file->name, $e->getMessage());
            throw new Exception($message);
        }
    }

    /**
     * Uploads multiple files and returns an array of paths to the uploaded files
     *
     * @param array<int|string, UploadedFile> $files An array of `UploadedFile` objects.
     * @param string $uploadPath The base path where files will be uploaded.
     * @param string $dirname An optional directory name to be appended to the upload path.
     * @param UploadFileValidatorInterface|null $validator An optional validator to validate the uploaded files.
     * @param callable|null $filenameCallback An optional callback function for customizing the filename.
     * @return array<int|string, string> An array of paths to the uploaded files.
     * @throws Exception If any error occurs during the upload process.
     */
    public static function uploadFiles(
        array $files,
        string $uploadPath,
        string $dirname = '',
        ?UploadFileValidatorInterface $validator = null,
        ?callable $filenameCallback = null
    ): array {
        $destFiles = [];
        $uploaders = [];
        $errors = [];

        try {
            foreach ($files as $i => $file) {
                $uploaders[$i] = new self($file, $uploadPath, $dirname);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            throw new Exception(implode(PHP_EOL, $errors));
        }

        try {
            foreach ($uploaders as $i => $uploader) {
                $destFiles[$i] = $uploader->uploadFile($validator, $filenameCallback);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            foreach ($destFiles as $destFile) {
                if (file_exists($destFile)) {
                    unlink($destFile);
                }
            }
            throw new Exception(implode(PHP_EOL, $errors));
        }

        return $destFiles;
    }

    /**
     * Deletes a file by its name
     *
     * @param string $filename
     * @return bool True on success.
     * @throws Exception If the deletion fails.
     */
    public static function deleteFile(string $filename): bool
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!isset($_SESSION['uploaded_files']) ||
                !is_array($_SESSION['uploaded_files']) ||
                !array_key_exists($filename, $_SESSION['uploaded_files'])) {
                throw new Exception('Unauthorized deletion attempt.');
            }

            $targetFile = $_SESSION['uploaded_files'][$filename];

            if (!file_exists($targetFile)) {
                throw new Exception('The file could not be found.');
            }

            if (!unlink($targetFile)) {
                throw new Exception('The file could not be remove from disk.');
            }

            unset($_SESSION['uploaded_files'][$filename]);

            return true;
        } catch (Exception $e) {
            $message = sprintf('%s delete failed: %s', $filename, $e->getMessage());
            throw new Exception($message);
        }
    }

    /**
     * Deletes multiple files
     *
     * @param array<int, string> $files An array of filenames to delete.
     * @return bool True on success, throws an exception on failure.
     * @throws Exception If any of the files cannot be deleted.
     */
    public static function deleteFiles(array $files): bool
    {
        $errors = [];

        try {
            foreach ($files as $file) {
                self::deleteFile($file);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            throw new Exception(implode(PHP_EOL, $errors));
        }

        return true;
    }

    /**
     * Get the original filename from the generated filename
     *
     * @param string $filename
     * @return string
     */
    public static function getOriginalFilename(string $filename): string
    {
        $originalFilename = preg_replace('/^\d{14}_\d{3}_[0-9a-z]{16}-/', '', $filename);
        return $originalFilename ?: $filename;
    }

    /**
     * Get the document root path of the server
     *
     * @return string
     * @throws Exception
     */
    private static function getDocumentRootPath(): string
    {
        $documentRoot = realpath($_SERVER['DOCUMENT_ROOT']);

        if ($documentRoot === false) {
            throw new Exception('Invalid document root path.');
        }

        return rtrim($documentRoot, '/');
    }

    /**
     * Validate the destination filename
     *
     * @param string $destFilename
     * @return void
     * @throws Exception
     */
    private static function validateDestFilename(string $destFilename): void
    {
        if (in_array($destFilename, ['', '.', '..'], true)) {
            throw new Exception('Invalid filename: Filename cannot be empty or a reserved name like "." or "..".');
        }
        if (preg_match('/[\/\\\\:*?"<>| \x00-\x1F]/', $destFilename)) {
            throw new Exception('Invalid filename: Filename contains illegal characters.');
        }
    }

    /**
     * Generate a unique filename based on the original filename
     *
     * @param string $filename
     * @return string
     * @throws Exception
     */
    private static function generateFilename(string $filename): string
    {
        // 获取当前微秒时间戳
        $microTime = microtime(true);
        // 获取整数字段（秒部分）和小数部分（微秒部分）
        $seconds = (int)floor($microTime);
        $microSeconds = $microTime - $seconds;

        // 将整数字段转换为可读时间格式
        $formattedTime = date('YmdHis', $seconds);
        // 将小数部分转为毫秒并保留原始值
        $milliseconds = (int)($microSeconds * 1000);

        $hashString = bin2hex(random_bytes(8));

        $destFilePrefix = sprintf('%s_%03d_%s-', $formattedTime, $milliseconds, $hashString);

        $filenameLengthLimit = 255 - strlen($destFilePrefix);

        if (strlen($filename) > $filenameLengthLimit) {
            $message = sprintf('Filename exceeds the maximum length of %d characters.', $filenameLengthLimit);
            throw new Exception($message);
        }

        $sanitizedFilename = preg_replace('/[\s-]+/', '_', $filename);
        if ($sanitizedFilename === null) {
            throw new Exception('An error occurred while sanitizing the filename.');
        }
        $sanitizedFilename = preg_replace('/_+/', '_', $sanitizedFilename);
        if ($sanitizedFilename === null) {
            throw new Exception('An error occurred while sanitizing the filename.');
        }
        $sanitizedFilename = trim($sanitizedFilename, '_');

        $fileInfo = pathinfo($sanitizedFilename);
        $destFilename = sprintf('%s%s', $destFilePrefix, $fileInfo['filename']);
        if (!empty($fileInfo['extension'])) {
            $destFilename .= sprintf('.%s', strtolower($fileInfo['extension']));
        }

        return $destFilename;
    }
}
