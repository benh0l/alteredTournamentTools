<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class AvatarUploadService
{
    public function __construct(
        private readonly string $avatarsDirectory,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Upload an avatar file and return the filename.
     */
    public function upload(UploadedFile $file): string
    {
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
