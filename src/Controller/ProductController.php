<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    #[Route('/legacy/products', name: 'legacy_create_product', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        // 1. Récupérer le JSON envoyé par l'utilisateur
        $jsonContent = $request->getContent();

        // 2. Transformer ce JSON en objet "Product" (Désérialisation)
        try {
            $product = $serializer->deserialize($jsonContent, Product::class, 'json');
        } catch (\Exception $e) {
            // Bubble the serializer failure message to help debugging payload issues
            return $this->json(['error' => $e->getMessage()], 400);
        }

        // 3. Sauvegarder en base de données
        $entityManager->persist($product);
        $entityManager->flush();

        // 4. Renvoyer le produit créé (Code 201 = Created)
        return $this->json($product, 201);
    }
}