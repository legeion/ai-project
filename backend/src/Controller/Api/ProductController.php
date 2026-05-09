<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\SubCategory;
use App\Entity\ProductAttributeValue;
use App\Entity\AttributeProduct;
use App\Entity\AttributeValue;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Entity\Shop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    // ==========================
    //  LISTE DES PRODUITS
    // ==========================
    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // =========================
        // CONSTRUCTION DE LA REQUÊTE DE BASE
        // =========================
        $qb = $em->createQueryBuilder()
            ->select('p', 'sc', 'c', 'pav', 'av', 'a', 'v', 'i', 'u', 'b')
            ->from(Product::class, 'p')
            ->leftJoin('p.subCategory', 'sc')
            ->leftJoin('sc.category', 'c')
            ->leftJoin('p.attributes', 'pav')
            ->leftJoin('pav.attribute', 'a')
            ->leftJoin('pav.value', 'av')
            ->leftJoin('p.variants', 'v')
            ->leftJoin('p.images', 'i')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.shop', 'b');

        // =========================
        // 🔎 RECHERCHE (nom produit)
        // =========================
        if ($search = $request->query->get('search')) {
            $qb->andWhere('p.name LIKE :search')
                ->setParameter('search', "%$search%");
        }

        // =========================
        // FILTRE PAR CATEGORIE
        // =========================
        if ($category = $request->query->get('category')) {
            $qb->andWhere('c.id = :cat')
                ->setParameter('cat', $category);
        }

        // =========================
        // FILTRE PAR ATTRIBUTS
        // =========================
        $filters = $request->query->all();

        foreach ($filters as $key => $value) {
            if (in_array($key, ['search', 'category', 'page', 'limit', 'minPrice', 'maxPrice', 'rayon', 'sort', 'order', 'status', 'user', 'shop'])) {
                continue;
            }

            $qb->andWhere('a.name = :attr_'.$key)
                ->andWhere('av.value = :val_'.$key)
                ->setParameter('attr_'.$key, $key)
                ->setParameter('val_'.$key, $value);
        }

        // =========================
        // FILTRE PRIX (VARIANTES)
        // =========================
        if ($min = $request->query->get('minPrice')) {
            $qb->andWhere('v.priceTtc >= :min')
                ->setParameter('min', $min);
        }

        if ($max = $request->query->get('maxPrice')) {
            $qb->andWhere('v.priceTtc <= :max')
                ->setParameter('max', $max);
        }

        // =========================
        // FILTRE RAYON
        // =========================
        if ($rayon = $request->query->get('rayon')) {
            $qb->andWhere('p.rayon = :rayon')
                ->setParameter('rayon', $rayon);
        }

        if (($status = $request->query->get('status')) !== null && $status !== '') {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        if ($user = $request->query->get('user')) {
            $qb->andWhere('u.id = :user')
                ->setParameter('user', $user);
        }

        if ($shop = $request->query->get('shop')) {
            $qb->andWhere('b.id = :shop')
                ->setParameter('shop', $shop);
        }

        // =========================
        // TRI
        // =========================
        $sort = $request->query->get('sort', 'id');
        $order = $request->query->get('order', 'ASC');

        $allowedSorts = ['id', 'name'];
        if (in_array($sort, $allowedSorts)) {
            $qb->orderBy('p.' . $sort, $order);
        }

        // =========================
        // COMPTER LE TOTAL (AVANT PAGINATION)
        // =========================
        $totalQb = clone $qb;
        $totalQb->select('COUNT(DISTINCT p.id) as total')
            ->setFirstResult(null)
            ->setMaxResults(null);

        // Solution 3a: Utiliser getSingleScalarResult()
        try {
            $total = (int) $totalQb->getQuery()->getSingleScalarResult();
        } catch (\Exception $e) {
            // Solution 3b: Fallback avec getResult()
            $totalResult = $totalQb->getQuery()->getResult();
            $total = !empty($totalResult) ? (int) $totalResult[0]['total'] : 0;
        }

        // =========================
        // PAGINATION
        // =========================
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, $request->query->getInt('limit', 10));

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->groupBy('p.id');

        $products = $qb->getQuery()->getResult();

        // =========================
        // FORMAT JSON
        // =========================
        $data = [];

        foreach ($products as $product) {
            $images = [];
            foreach ($product->getImages() as $img) {
                $imagePath = $this->normalizeImagePath($img->getImagePath());
                $images[] = [
                    'id' => $img->getId(),
                    'path' => $imagePath,
                    'url' => $this->buildImageUrl($request, $imagePath)
                ];
            }

            $attributes = [];
            foreach ($product->getAttributes() as $attr) {
                $attributes[] = [
                    'id' => $attr->getId(),
                    'attribute_id' => $attr->getAttribute()->getId(),
                    'attribute_name' => $attr->getAttribute()->getName(),
                    'value_id' => $attr->getValue()->getId(),
                    'value_name' => $attr->getValue()->getValue()
                ];
            }

            $variants = [];
            foreach ($product->getVariants() as $v) {
                $variants[] = [
                    'id' => $v->getId(),
                    'price' => $v->getPriceTtc(),
                    'price_ht' => $v->getPriceHt(),
                    'price_ttc' => $v->getPriceTtc(),
                    'priceHt' => $v->getPriceHt(),
                    'priceTtc' => $v->getPriceTtc(),
                    'stock' => $v->getStock()
                ];
            }

            $data[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'status' => $product->isStatus(),
                'tva' => $product->getTva(),
                'category' => $product->getSubCategory()?->getCategory() ? [
                    'id' => $product->getSubCategory()?->getCategory()?->getId(),
                    'name' => $product->getSubCategory()?->getCategory()?->getName()
                ] : null,
                'subCategory' => $product->getSubCategory() ? [
                    'id' => $product->getSubCategory()?->getId(),
                    'name' => $product->getSubCategory()?->getName()
                ] : null,
                'rayon' => [
                    'id' => $product->getRayon()?->getId(),
                    'name' => $product->getRayon()?->getName()
                ],
                'user' => $product->getUser() ? [
                    'id' => $product->getUser()?->getId(),
                    'name' => $product->getUser()?->getName(),
                    'email' => $product->getUser()?->getEmail()
                ] : null,
                'shop' => $product->getShop() ? [
                    'id' => $product->getShop()?->getId(),
                    'name' => $product->getShop()?->getName(),
                    'city' => $product->getShop()?->getCity()
                ] : null,
                'images' => $images,
                'attributes' => $attributes,
                'variants' => $variants,
                'stats' => [
                    'total_stock' => array_sum(array_column($variants, 'stock')),
                    'min_price' => !empty($variants) ? min(array_column($variants, 'price_ttc')) : null,
                    'max_price' => !empty($variants) ? max(array_column($variants, 'price_ttc')) : null,
                    'min_price_ht' => !empty($variants) ? min(array_column($variants, 'price_ht')) : null,
                    'max_price_ht' => !empty($variants) ? max(array_column($variants, 'price_ht')) : null,
                    'min_price_ttc' => !empty($variants) ? min(array_column($variants, 'price_ttc')) : null,
                    'max_price_ttc' => !empty($variants) ? max(array_column($variants, 'price_ttc')) : null,
                    'variants_count' => count($variants),
                    'images_count' => count($images)
                ]
            ];
        }

        return $this->json([
            'success' => true,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $total,
                'total_pages' => ceil($total / $limit),
                'has_next_page' => $page < ceil($total / $limit),
                'has_previous_page' => $page > 1
            ],
            'data' => $data
        ]);
    }

    // ==========================
    // AJOUT D'UN PRODUIT (attributs + images + variantes)
    // ==========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $product = new Product();
        $product->setName($data['name']);

        $subCategory = $em->getRepository(SubCategory::class)->find($data['subCategory'] ?? null);
        $product->setSubCategory($subCategory);
        $product->setRayon($subCategory?->getCategory()?->getRayon());
        $product->setStatus((bool) ($data['status'] ?? true));
        $product->setTva((float) ($data['tva'] ?? 0));

        if (!empty($data['user'])) {
            $user = $em->getRepository(User::class)->find($data['user']);
            $product->setUser($user);
        }

        if (!empty($data['shop'])) {
            $shop = $em->getRepository(Shop::class)->find($data['shop']);
            $product->setShop($shop);
        }

        $em->persist($product);

        // ======================
        // ATTRIBUTS
        // ======================
        foreach (($data['attributes'] ?? []) as $attr) {
            $pav = new ProductAttributeValue();

            $attribute = $em->getRepository(AttributeProduct::class)->find($attr['attribute']);
            $category = $product->getSubCategory()?->getCategory();
            if (!$category || !$category->getAttributes()->contains($attribute)) {
                throw new \Exception("Attribut non autorisé pour cette catégorie");
            }
            $value = $em->getRepository(AttributeValue::class)->find($attr['value']);

            $pav->setProduct($product);
            $pav->setAttribute($attribute);
            $pav->setValue($value);

            $em->persist($pav);
        }

        // ======================
        // IMAGES
        // ======================
        foreach (($data['images'] ?? []) as $img) {
            $this->persistBase64ProductImage($em, $product, $img);
        }

        // ======================
        // VARIANTES
        // ======================
        foreach (($data['variants'] ?? []) as $variantData) {
            $variant = new ProductVariant();
            $priceHt = (float) ($variantData['priceHt'] ?? $variantData['price_ht'] ?? $variantData['price'] ?? 0);
            $priceTtc = (float) ($variantData['priceTtc'] ?? $variantData['price_ttc'] ?? 0);

            if ($priceTtc <= 0 && $priceHt > 0) {
                $priceTtc = $priceHt * (1 + $product->getTva());
            }

            $variant->setProduct($product);
            $variant->setPriceHt($priceHt);
            $variant->setPriceTtc($priceTtc);
            $variant->setStock($variantData['stock']);

            $em->persist($variant);
        }

        $em->flush();

        return $this->json(['message' => 'Produit complet créé']);
    }

    // ==========================
    // AJOUT D'UN PRODUIT (Version hybride avec retour complet)
    // ==========================
    #[Route('/produit-hybryde', methods: ['POST'])]
    public function createHybride(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Détecter le type de contenu
        $contentType = $request->headers->get('Content-Type');
        $data = [];

        // Cas 1: JSON (sans fichiers)
        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);

            // Validation des champs requis
            if (!isset($data['name']) || !isset($data['category'])) {
                return $this->json(['error' => 'Nom et catégorie requis'], 400);
            }

            $name = $data['name'];
            $categoryId = $data['category'];
            $attributes = $data['attributes'] ?? [];
            $variants = $data['variants'] ?? [];
            $imagesBase64 = $data['images'] ?? []; // Images en base64 (optionnel)

        }
        // Cas 2: multipart/form-data (avec fichiers)
        else {
            $name = $request->get('name');
            $categoryId = $request->get('category');
            $attributes = json_decode($request->get('attributes', '[]'), true);
            $variants = json_decode($request->get('variants', '[]'), true);
            $uploadedFiles = $request->files->get('images');
        }

        // Validation commune
        if (!$name || !$categoryId) {
            return $this->json(['error' => 'Nom et sous-catégorie requis'], 400);
        }

        // Création du produit
        $product = new Product();
        $product->setName($name);
        $subCategory = $em->getRepository(SubCategory::class)->find($categoryId);
        if (!$subCategory) {
            return $this->json(['error' => 'Sous-catégorie non trouvée'], 404);
        }
        $product->setSubCategory($subCategory);
        $product->setRayon($subCategory->getCategory()?->getRayon());
        $product->setStatus((bool) ($data['status'] ?? $request->get('status', true)));
        $product->setTva((float) ($data['tva'] ?? $request->get('tva', 0)));

        $userId = $data['user'] ?? $request->get('user');
        if ($userId) {
            $user = $em->getRepository(User::class)->find($userId);
            $product->setUser($user);
        }

        $boutiqueId = $data['shop'] ?? $request->get('shop');
        if ($boutiqueId) {
            $shop = $em->getRepository(Shop::class)->find($boutiqueId);
            $product->setShop($shop);
        }
        $em->persist($product);

        // Tableaux pour stocker les données à retourner
        $savedAttributes = [];
        $savedVariants = [];
        $savedImages = [];

        // ======================
        // ATTRIBUTS
        // ======================
        foreach ($attributes as $attr) {
            $pav = new ProductAttributeValue();
            $attribute = $em->getRepository(AttributeProduct::class)->find($attr['attribute']);

            $category = $product->getSubCategory()?->getCategory();
            if (!$attribute || !$category || !$category->getAttributes()->contains($attribute)) {
                continue; // Ignorer les attributs invalides
            }

            $value = $em->getRepository(AttributeValue::class)->find($attr['value']);
            if (!$value) continue;

            $pav->setProduct($product);
            $pav->setAttribute($attribute);
            $pav->setValue($value);
            $em->persist($pav);
            // Stocker pour le retour
            $savedAttributes[] = [
                'id' => $pav->getId(),
                'attribute_id' => $attribute->getId(),
                'attribute_name' => $attribute->getName(),
                'value_id' => $value->getId(),
                'value_name' => $value->getValue()
            ];
        }

        // ======================
        // IMAGES (gestion des deux cas)
        // ======================

        // Cas 1: Images en base64 (depuis JSON)
        if (isset($imagesBase64) && is_array($imagesBase64)) {
            foreach ($imagesBase64 as $index => $imgData) {
                if (preg_match('/^data:image\/(\w+);base64,/', $imgData, $matches)) {
                    $imageType = $matches[1];
                    $imageData = substr($imgData, strpos($imgData, ',') + 1);
                    $decodedImage = base64_decode($imageData);

                    if ($decodedImage && strlen($decodedImage) <= 2 * 1024 * 1024) {
                        $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
                        $fileName = uniqid('product_') . '_' . time() . '_' . $index . '.' . $extension;
                        $uploadPath = $this->getParameter('product_images_directory');
                        if (!file_exists($uploadPath)) {
                            mkdir($uploadPath, 0777, true);
                        }
                        file_put_contents($uploadPath . '/' . $fileName, $decodedImage);
                        $image = new ProductImage();
                        $image->setImagePath($fileName);
                        $image->setProduct($product);
                        $em->persist($image);
                        // Stocker pour le retour
                        $savedImages[] = [
                            'id' => $image->getId(),
                            'path' => $fileName,
                            'url' => $this->buildImageUrl($request, $fileName)
                        ];
                    }
                }
            }
        }

        // Cas 2: Fichiers uploadés (depuis form-data)
        if (isset($uploadedFiles) && is_array($uploadedFiles)) {
            foreach ($uploadedFiles as $file) {
                if (!$file) continue;
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($file->guessExtension(), $allowedExtensions)) {
                    continue;
                }

                if ($file->getSize() > 2 * 1024 * 1024) {
                    continue;
                }

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $originalName);
                $newFilename = $safeName . '_' . uniqid() . '.' . $file->guessExtension();

                try {
                    $file->move($this->getParameter('product_images_directory'), $newFilename);

                    $image = new ProductImage();
                    $image->setImagePath($newFilename);
                    $image->setProduct($product);
                    $em->persist($image);

                    // Stocker pour le retour
                    $savedImages[] = [
                        'id' => $image->getId(),
                        'path' => $newFilename,
                        'url' => $this->buildImageUrl($request, $newFilename),
                        'original_name' => $file->getClientOriginalName()
                    ];

                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // ======================
        // VARIANTES
        // ======================
        foreach ($variants as $variantData) {
            $variant = new ProductVariant();
            $priceHt = (float) ($variantData['priceHt'] ?? $variantData['price_ht'] ?? $variantData['price'] ?? 0);
            $priceTtc = (float) ($variantData['priceTtc'] ?? $variantData['price_ttc'] ?? 0);

            if ($priceTtc <= 0 && $priceHt > 0) {
                $priceTtc = $priceHt * (1 + $product->getTva());
            }

            $variant->setProduct($product);
            $variant->setPriceHt($priceHt);
            $variant->setPriceTtc($priceTtc);
            $variant->setStock($variantData['stock'] ?? 0);
            $em->persist($variant);

            // Stocker pour le retour
            $savedVariants[] = [
                'id' => $variant->getId(),
                'price' => $variant->getPriceTtc(),
                'price_ht' => $variant->getPriceHt(),
                'price_ttc' => $variant->getPriceTtc(),
                'priceHt' => $variant->getPriceHt(),
                'priceTtc' => $variant->getPriceTtc(),
                'stock' => $variant->getStock()
            ];
        }

        $em->flush();

        // Retourner toutes les informations du produit
        return $this->json([
            'message' => 'Produit créé avec succès',
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'status' => $product->isStatus(),
                'tva' => $product->getTva(),
                'category' => $product->getSubCategory()?->getCategory() ? [
                    'id' => $product->getSubCategory()?->getCategory()?->getId(),
                    'name' => $product->getSubCategory()?->getCategory()?->getName()
                ] : null,
                'subCategory' => $product->getSubCategory() ? [
                    'id' => $product->getSubCategory()?->getId(),
                    'name' => $product->getSubCategory()?->getName()
                ] : null,
                'rayon' => [
                    'id' => $product->getRayon()?->getId(),
                    'name' => $product->getRayon()?->getName()
                ],
                'user' => $product->getUser() ? [
                    'id' => $product->getUser()?->getId(),
                    'name' => $product->getUser()?->getName(),
                    'email' => $product->getUser()?->getEmail()
                ] : null,
                'shop' => $product->getShop() ? [
                    'id' => $product->getShop()?->getId(),
                    'name' => $product->getShop()?->getName(),
                    'city' => $product->getShop()?->getCity()
                ] : null,
                'images' => $savedImages,
                'attributes' => $savedAttributes,
                'variants' => $savedVariants,
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ]
        ], 201); // Code 201 pour création
    }

    // ==========================
    // DÉTAIL PRODUIT
    // ==========================
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product, Request $request): JsonResponse
    {
        // Images avec plus de détails
        $images = [];
        foreach ($product->getImages() as $img) {
            $imagePath = $this->normalizeImagePath($img->getImagePath());
            $images[] = [
                'id' => $img->getId(),
                'path' => $imagePath,
                'url' => $this->buildImageUrl($request, $imagePath)
            ];
        }

        // Attributs avec plus de détails
        $attributes = [];
        foreach ($product->getAttributes() as $attr) {
            $attributes[] = [
                'id' => $attr->getId(),
                'attribute_id' => $attr->getAttribute()->getId(),
                'attribute_name' => $attr->getAttribute()->getName(),
                'value_id' => $attr->getValue()->getId(),
                'value_name' => $attr->getValue()->getValue()
            ];
        }

        // Variantes avec plus de détails
        $variants = [];
        foreach ($product->getVariants() as $v) {
            $variants[] = [
                'id' => $v->getId(),
                'price' => $v->getPriceTtc(),
                'price_ht' => $v->getPriceHt(),
                'price_ttc' => $v->getPriceTtc(),
                'priceHt' => $v->getPriceHt(),
                'priceTtc' => $v->getPriceTtc(),
                'stock' => $v->getStock()
            ];
        }

        // Retourner sans le wrapper 'product' (comme la réponse de création)
        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'status' => $product->isStatus(),
            'tva' => $product->getTva(),
            'category' => $product->getSubCategory()?->getCategory() ? [
                'id' => $product->getSubCategory()?->getCategory()?->getId(),
                'name' => $product->getSubCategory()?->getCategory()?->getName()
            ] : null,
            'subCategory' => $product->getSubCategory() ? [
                'id' => $product->getSubCategory()?->getId(),
                'name' => $product->getSubCategory()?->getName()
            ] : null,
            'rayon' => [
                'id' => $product->getRayon()?->getId(),
                'name' => $product->getRayon()?->getName()
            ],
            'user' => $product->getUser() ? [
                'id' => $product->getUser()?->getId(),
                'name' => $product->getUser()?->getName(),
                'email' => $product->getUser()?->getEmail()
            ] : null,
            'shop' => $product->getShop() ? [
                'id' => $product->getShop()?->getId(),
                'name' => $product->getShop()?->getName(),
                'city' => $product->getShop()?->getCity()
            ] : null,
            'images' => $images,
            'attributes' => $attributes,
            'variants' => $variants
        ]);
    }

    // ==========================
    //  UPDATE PRODUIT (attributs + images + variantes)
    // ==========================
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $product = $em->getRepository(Product::class)->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // 📝 Nom
        if (!empty($data['name'])) {
            $product->setName($data['name']);
        }

        if (array_key_exists('status', $data)) {
            $product->setStatus((bool) $data['status']);
        }

        if (array_key_exists('tva', $data)) {
            $product->setTva((float) $data['tva']);
        }

        // 📂 Catégorie + Rayon auto
        if (!empty($data['subCategory'])) {
            $subCategory = $em->getRepository(SubCategory::class)->find($data['subCategory']);
            $product->setSubCategory($subCategory);
            $product->setRayon($subCategory?->getCategory()?->getRayon());
        }

        if (array_key_exists('user', $data)) {
            $user = $data['user']
                ? $em->getRepository(User::class)->find($data['user'])
                : null;
            $product->setUser($user);
        }

        if (array_key_exists('shop', $data)) {
            $shop = $data['shop']
                ? $em->getRepository(Shop::class)->find($data['shop'])
                : null;
            $product->setShop($shop);
        }

        // 🔁 Attributs (remplacement total)
        if (array_key_exists('attributes', $data)) {
            foreach ($product->getAttributes()->toArray() as $productAttributeValue) {
                $product->removeAttribute($productAttributeValue);
                $em->remove($productAttributeValue);
            }

            foreach ($data['attributes'] as $attr) {
                $attribute = $em->getRepository(AttributeProduct::class)->find($attr['attribute'] ?? null);
                $value = $em->getRepository(AttributeValue::class)->find($attr['value'] ?? null);

                if (!$attribute || !$value) {
                    continue;
                }

                $category = $product->getSubCategory()?->getCategory();
                if (!$category || !$category->getAttributes()->contains($attribute)) {
                    continue;
                }

                $pav = new ProductAttributeValue();
                $pav->setProduct($product);
                $pav->setAttribute($attribute);
                $pav->setValue($value);
                $em->persist($pav);
            }
        }

        // 🔁 Variantes (remplacement total)
        if (array_key_exists('variants', $data)) {
            foreach ($product->getVariants()->toArray() as $variant) {
                $em->remove($variant);
            }

            foreach ($data['variants'] as $variantData) {
                $variant = new ProductVariant();
                $priceHt = (float) ($variantData['priceHt'] ?? $variantData['price_ht'] ?? $variantData['price'] ?? 0);
                $priceTtc = (float) ($variantData['priceTtc'] ?? $variantData['price_ttc'] ?? 0);

                if ($priceTtc <= 0 && $priceHt > 0) {
                    $priceTtc = $priceHt * (1 + $product->getTva());
                }

                $variant->setProduct($product);
                $variant->setPriceHt($priceHt);
                $variant->setPriceTtc($priceTtc);
                $variant->setStock((int) ($variantData['stock'] ?? 0));
                $em->persist($variant);
            }
        }

        // 🖼️ Images (remplacement total)
        if (array_key_exists('images', $data)) {
            $existingImages = [];
            $filesToDelete = [];

            foreach ($product->getImages()->toArray() as $img) {
                $existingImages[$this->normalizeImagePath($img->getImagePath())] = $img;
            }

            $nextImages = $data['images'] ?? [];
            $keptPaths = [];

            foreach ($nextImages as $imageItem) {
                if (is_string($imageItem) && !str_starts_with($imageItem, 'data:image/')) {
                    $keptPaths[] = $this->normalizeImagePath($imageItem);
                }
            }

            foreach ($existingImages as $path => $img) {
                if (!in_array($path, $keptPaths, true)) {
                    $filesToDelete[] = $this->getParameter('product_images_directory') . '/' . $this->normalizeImagePath($img->getImagePath());
                    $product->removeImage($img);
                    $em->remove($img);
                }
            }

            foreach ($nextImages as $imageItem) {
                if (!is_string($imageItem)) {
                    continue;
                }

                if (!str_starts_with($imageItem, 'data:image/')) {
                    $normalizedImagePath = $this->normalizeImagePath($imageItem);

                    if (isset($existingImages[$normalizedImagePath])) {
                        continue;
                    }

                    $image = new ProductImage();
                    $image->setImagePath($normalizedImagePath);
                    $image->setProduct($product);
                    $em->persist($image);
                    continue;
                }

                $this->persistBase64ProductImage($em, $product, $imageItem);
            }

            foreach ($filesToDelete as $filePath) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }

        $em->flush();

        return $this->json(['message' => 'Produit mis à jour']);
    }

    // ==========================
    // SUPPRIMER PRODUIT
    // ==========================
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $productId = $product->getId();
        $productName = $product->getName();
        $filesToDelete = [];

        foreach ($product->getImages()->toArray() as $image) {
            $filesToDelete[] = $this->getParameter('product_images_directory') . '/' . $this->normalizeImagePath($image->getImagePath());
            $product->removeImage($image);
            $em->remove($image);
        }

        foreach ($product->getAttributes()->toArray() as $attribute) {
            $product->removeAttribute($attribute);
            $em->remove($attribute);
        }

        foreach ($product->getVariants()->toArray() as $variant) {
            $em->remove($variant);
        }

        $em->remove($product);
        $em->flush();

        $deletedCount = 0;
        $failedDeletions = [];

        foreach ($filesToDelete as $filePath) {
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedCount++;
                } else {
                    $failedDeletions[] = basename($filePath);
                }
            }
        }

        return $this->json([
            'message' => 'Produit supprimé avec succès',
            'product' => [
                'id' => $productId,
                'name' => $productName
            ],
            'files_deleted' => $deletedCount,
            'files_failed' => $failedDeletions
        ]);
    }

    private function normalizeImagePath(string $path): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return '';
        }

        return basename(str_replace('\\', '/', $trimmedPath));
    }

    private function buildImageUrl(Request $request, string $imagePath): string
    {
        $normalizedPath = $this->normalizeImagePath($imagePath);

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/uploads/products/' . $normalizedPath;
    }

    private function persistBase64ProductImage(EntityManagerInterface $em, Product $product, mixed $imageData): void
    {
        if (!is_string($imageData) || $imageData === '') {
            return;
        }

        if (!preg_match('/^data:image\/([a-zA-Z0-9+]+);base64,/', $imageData, $matches)) {
            return;
        }

        $rawBase64 = substr($imageData, strpos($imageData, ',') + 1);
        $decodedImage = base64_decode($rawBase64, true);

        if ($decodedImage === false) {
            return;
        }

        $imageType = strtolower($matches[1]);
        $extension = $imageType === 'jpeg' ? 'jpg' : $imageType;
        $allowedExtensions = ['jpg', 'png', 'webp', 'gif'];

        if (!in_array($extension, $allowedExtensions, true)) {
            $extension = 'jpg';
        }

        $uploadPath = $this->getParameter('product_images_directory');

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $fileName = uniqid('product_', true) . '.' . $extension;
        file_put_contents($uploadPath . '/' . $fileName, $decodedImage);

        $image = new ProductImage();
        $image->setImagePath($fileName);
        $image->setProduct($product);

        $em->persist($image);
    }

}
