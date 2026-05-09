<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/upload')]
class UploadController extends AbstractController
{
    #[Route('/product-image', methods: ['POST'])]
    public function uploadProductImage(Request $request): JsonResponse
    {
        $file = $request->files->get('image');

        if (!$file) {
            return $this->json(['error' => 'Aucun fichier envoyé'], 400);
        }

        // Vérification extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($file->guessExtension(), $allowedExtensions)) {
            return $this->json(['error' => 'Format non autorisé'], 400);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['error' => 'Type MIME invalide'], 400);
        }

        // Vérification taille (2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->json(['error' => 'Fichier trop volumineux'], 400);
        }

        // Nom unique
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $originalName);
        $newFilename = $safeName . '_' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('product_images_directory'),
                $newFilename
            );
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur upload'], 500);
        }

        return $this->json([
            'message' => 'Upload réussi',
            'url' => '/uploads/products/' . $newFilename
        ]);
    }
}
