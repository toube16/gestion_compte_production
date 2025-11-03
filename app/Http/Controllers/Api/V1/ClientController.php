<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @group Clients
 *
 * APIs pour la gestion des clients bancaires
 */
class ClientController extends Controller
{
    use ApiResponseTrait;

    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     summary="Lister tous les clients",
     *     description="Récupère la liste paginée des clients selon les permissions de l'utilisateur. Admin voit tous les clients, Client voit uniquement son propre profil.",
     *     operationId="getClients",
     *     tags={"Clients"},
     *     security={{"passport": {"read-clients"}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Recherche par nom, prénom ou email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Tri",
     *         required=false,
     *         @OA\Schema(type="string", enum={"nom", "prenom", "email", "dateCreation"})
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Ordre",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des clients récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Clients récupérés avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Client")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 @OA\Property(property="currentPage", type="integer", example=1),
     *                 @OA\Property(property="totalPages", type="integer", example=3),
     *                 @OA\Property(property="totalItems", type="integer", example=25),
     *                 @OA\Property(property="itemsPerPage", type="integer", example=10),
     *                 @OA\Property(property="hasNext", type="boolean", example=true),
     *                 @OA\Property(property="hasPrevious", type="boolean", example=false)
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 @OA\Property(property="self", type="string", example="/api/v1/clients?page=1&limit=10"),
     *                 @OA\Property(property="next", type="string", example="/api/v1/clients?page=2&limit=10"),
     *                 @OA\Property(property="first", type="string", example="/api/v1/clients?page=1&limit=10"),
     *                 @OA\Property(property="last", type="string", example="/api/v1/clients?page=3&limit=10")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accès non autorisé")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validation des paramètres
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'search' => 'string|nullable',
            'sort' => 'string|in:nom,prenom,email,dateCreation',
            'order' => 'string|in:asc,desc',
        ]);

        $query = Client::with('comptes');

        // Filtrage selon le rôle de l'utilisateur
        if ($user->role === 'client') {
            // Les utilisateurs clients ne voient que leur propre profil
            $query->where('id', $user->client_id);
        }
        // Les admins voient tous les clients (pas de filtrage supplémentaire)

        // Application des filtres
        if (!empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Tri
        $sortField = match($validated['sort'] ?? 'dateCreation') {
            'nom' => 'nom',
            'prenom' => 'prenom',
            'email' => 'email',
            'dateCreation' => 'created_at',
            default => 'created_at'
        };

        $order = $validated['order'] ?? 'desc';
        $query->orderBy($sortField, $order);

        // Pagination
        $perPage = $validated['limit'] ?? 10;
        $clients = $query->paginate($perPage);

        return $this->paginatedResponse($clients, ClientResource::class);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{clientId}",
     *     summary="Récupérer un client spécifique",
     *     description="Récupère les détails d'un client spécifique selon les permissions de l'utilisateur.",
     *     operationId="getClient",
     *     tags={"Clients"},
     *     security={{"passport": {"read-clients"}}},
     *     @OA\Parameter(
     *         name="clientId",
     *         in="path",
     *         description="ID du client à récupérer",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails du client récupérés avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Client récupéré avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Client")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le client avec l'ID spécifié n'existe pas")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Non authentifié")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accès non autorisé")
     *         )
     *     )
     * )
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();

        // Vérifier les permissions selon le rôle
        if ($user->role === 'client') {
            // Les clients ne peuvent voir que leur propre profil — l'ID en URL ne doit pas permettre
            // d'accéder à un autre client. On vérifie que le client demandé appartient bien à l'utilisateur.
            $clientIds = $user->clients->pluck('id');
            if (!in_array($client->id, $clientIds->toArray())) {
                throw new UnauthorizedAccessException("Vous n'avez pas accès à ce client.");
            }
        }
        // Les admins peuvent voir tous les clients

        return $this->successResponse(
            new ClientResource($client->load('comptes')),
            'Client récupéré avec succès'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{clientId}/comptes",
     *     summary="Lister les comptes d'un client",
     *     description="Récupère la liste des comptes associés à un client spécifique. Seuls les propriétaires ou les admins peuvent accéder.",
     *     operationId="getClientComptes",
     *     tags={"Clients"},
     *     security={{"passport": {"read-clients"}}},
     *     @OA\Parameter(
     *         name="clientId",
     *         in="path",
     *         description="ID du client",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Numéro de page",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"epargne", "cheque"})
     *     ),
     *     @OA\Parameter(
     *         name="statut",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"actif", "bloque", "ferme"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des comptes du client récupérée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Comptes du client récupérés avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Compte")
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 @OA\Property(property="currentPage", type="integer", example=1),
     *                 @OA\Property(property="totalPages", type="integer", example=1),
     *                 @OA\Property(property="totalItems", type="integer", example=2),
     *                 @OA\Property(property="itemsPerPage", type="integer", example=10),
     *                 @OA\Property(property="hasNext", type="boolean", example=false),
     *                 @OA\Property(property="hasPrevious", type="boolean", example=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accès non autorisé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le client avec l'ID spécifié n'existe pas")
     *         )
     *     )
     * )
     */
    public function comptes(Request $request, Client $client): JsonResponse
    {
        $user = $request->user();
        // Vérifier les permissions selon le rôle
        if ($user->role === 'client') {
            // Pour les utilisateurs clients, s'assurer qu'ils accèdent uniquement aux comptes du client lié
            $clientIds = $user->clients->pluck('id');
            if (!in_array($client->id, $clientIds->toArray())) {
                throw new UnauthorizedAccessException("Vous n'avez pas accès aux comptes de ce client.");
            }
        }
        // Les admins peuvent voir les comptes de tous les clients

        // Validation des paramètres
        $validated = $request->validate([
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1|max:100',
            'type' => 'string|in:epargne,cheque',
            'statut' => 'string|in:actif,bloque,ferme',
        ]);

        $query = $client->comptes();

        // Application des filtres
        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['statut'])) {
            $query->where('statut', $validated['statut']);
        }

        // Exclure les comptes bloqués et supprimés pour les clients
        if ($user->role === 'client') {
            $query->where('statut', '!=', 'bloque')
                  ->whereNull('deleted_at');
        }

        // Tri par défaut : date de création décroissante
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $validated['limit'] ?? 10;
        $comptes = $query->paginate($perPage);

        return $this->paginatedResponse($comptes, \App\Http\Resources\CompteResource::class, 'Comptes du client récupérés avec succès');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/clients/{clientId}",
     *     summary="Mettre à jour un client",
     *     description="Permet aux clients de modifier leurs informations personnelles. Tous les champs sont optionnels mais au moins un doit être fourni.",
     *     operationId="updateClient",
     *     tags={"Clients"},
     *     security={{"passport": {"write-clients"}}},
     *     @OA\Parameter(
     *         name="clientId",
     *         in="path",
     *         description="ID du client à modifier",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="nom", type="string", example="Diallo"),
     *             @OA\Property(property="prenom", type="string", example="Amadou Junior"),
     *             @OA\Property(property="email", type="string", format="email", example="nouveau.email@example.com"),
     *             @OA\Property(property="telephone", type="string", example="+221771234568"),
     *             @OA\Property(property="adresse", type="string", example="Nouvelle adresse, Dakar"),
     *             @OA\Property(property="date_naissance", type="string", format="date", example="1990-05-15")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Client mis à jour avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Client mis à jour avec succès"),
     *             @OA\Property(property="data", ref="#/components/schemas/Client")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Données invalides ou aucun champ fourni",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Les données fournies sont invalides")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès refusé - client n'appartient pas à l'utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Accès non autorisé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Client non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le client avec l'ID spécifié n'existe pas")
     *         )
     *     )
     * )
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $user = $request->user();
        // Vérifier les permissions : seuls les propriétaires peuvent modifier
        if ($user->role === 'client') {
            $clientIds = $user->clients->pluck('id');
            if (!in_array($client->id, $clientIds->toArray())) {
                throw new UnauthorizedAccessException("Vous n'avez pas accès à ce client.");
            }
        }
        // Les admins peuvent modifier tous les clients

        $validated = $request->validated();

        $client->update($validated);

        Log::info('Client mis à jour', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'champs_modifies' => array_keys($validated),
        ]);

        return $this->successResponse(
            new ClientResource($client->load('comptes')),
            'Client mis à jour avec succès'
        );
    }
}