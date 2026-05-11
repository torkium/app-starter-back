<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ListLegalDocumentsController
{
    #[Route('/api/legal-documents', name: 'api_legal_documents', methods: ['GET'])]
    public function __invoke(Request $request, IdentityManager $identityManager): JsonResponse
    {
        $locale = $request->query->get('locale');

        return new JsonResponse($identityManager->listActiveLegalDocuments(is_string($locale) ? $locale : null));
    }
}
