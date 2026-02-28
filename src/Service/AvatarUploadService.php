<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AvatarUploadService
{
    /**
     * Allowed MIME types for avatar uploads.
     */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Maximum file size in bytes (2 MB).
     */
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    public function __construct(
        private readonly string $avatarsDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Upload an avatar file and return the filename.
     *
     * @throws \InvalidArgumentException If the file type or size is invalid
     * @throws \RuntimeException If the file cannot be moved
     */
    public function upload(UploadedFile $file): string
    {
        // Defensive validation: ensure MIME type is allowed
        $mimeType = $file->getMimeType();
        if (!$mimeType || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(
                'Type de fichier non autorise. Formats acceptes: JPG, PNG, GIF, WebP.'
            );
        }

        // Defensive validation: check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                'Le fichier depasse la taille maximale autorisee de 2 Mo.'
            );
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->avatarsDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Erreur lors de l\'upload de l\'avatar: ' . $e->getMessage());
        }

        return $newFilename;
    }

    /**
     * Delete an avatar file.
     */
    public function delete(?string $filename): void
    {
        if ($filename === null) {
            return;
        }

        $filepath = $this->avatarsDirectory . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Get the public path for an avatar.
     */
    public function getPublicPath(string $filename): string
    {
        return '/uploads/avatars/' . $filename;
    }
}
