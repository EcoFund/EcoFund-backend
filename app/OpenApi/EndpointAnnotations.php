<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class EndpointAnnotations
{
    // ========================================================================
    // AUTHENTICATION
    // ========================================================================
    #[OA\Post(path: '/auth/login', tags: ['Auth'], summary: 'Login',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'password', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function authLogin() {}

    #[OA\Post(path: '/auth/register', tags: ['Auth'], summary: 'Register',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'nama', type: 'string'), new OA\Property(property: 'email', type: 'string'), new OA\Property(property: 'password', type: 'string'), new OA\Property(property: 'role', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function authRegister() {}

    #[OA\Post(path: '/auth/logout', tags: ['Auth'], summary: 'Logout', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function authLogout() {}

    #[OA\Get(path: '/auth/me', tags: ['Auth'], summary: 'Get current user', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function authMe() {}

    #[OA\Post(path: '/auth/me', tags: ['Auth'], summary: 'Update profile', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function authUpdateMe() {}

    #[OA\Post(path: '/auth/google', tags: ['Auth'], summary: 'Google Auth',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'token', type: 'string')])),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function authGoogle() {}

    // ========================================================================
    // ADMIN
    // ========================================================================
    #[OA\Get(path: '/admin/stats', tags: ['Admin'], summary: 'Admin Stats', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function adminStats() {}

    #[OA\Get(path: '/admin/users', tags: ['Admin'], summary: 'List Users', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function adminUsers() {}

    // ========================================================================
    // CAMPAIGNS
    // ========================================================================
    #[OA\Get(path: '/campaigns', tags: ['Campaigns'], summary: 'List all active campaigns', responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campIndex() {}

    #[OA\Post(path: '/campaigns', tags: ['Campaigns'], summary: 'Create campaign', security: [['sanctum' => []]], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function campStore() {}

    #[OA\Get(path: '/campaigns/categories', tags: ['Campaigns'], summary: 'Get categories', responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campCats() {}

    #[OA\Get(path: '/campaigns/my', tags: ['Campaigns'], summary: 'Get my campaigns', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campMy() {}

    #[OA\Get(path: '/campaigns/{campaign}', tags: ['Campaigns'], summary: 'Get campaign details', parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campShow() {}

    #[OA\Post(path: '/campaigns/{campaign}', tags: ['Campaigns'], summary: 'Update campaign', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campUpdate() {}

    #[OA\Delete(path: '/campaigns/{campaign}', tags: ['Campaigns'], summary: 'Delete campaign', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campDel() {}

    #[OA\Patch(path: '/campaigns/{campaign}/approve', tags: ['Admin Campaigns'], summary: 'Approve campaign', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campApprove() {}

    #[OA\Patch(path: '/campaigns/{campaign}/reject', tags: ['Admin Campaigns'], summary: 'Reject campaign', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function campReject() {}

    // ========================================================================
    // DONATIONS
    // ========================================================================
    #[OA\Post(path: '/donations/campaigns/{campaign}', tags: ['Donations'], summary: 'Create donation (Xendit)', parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'amount', type: 'integer'), new OA\Property(property: 'nama_donatur', type: 'string'), new OA\Property(property: 'anonymous', type: 'boolean')])),
        responses: [new OA\Response(response: 200, description: 'Success')]
    )]
    public function donStore() {}

    #[OA\Post(path: '/donations/confirm', tags: ['Donations'], summary: 'Xendit Webhook', responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donConfirm() {}

    #[OA\Get(path: '/campaigns/{campaign}/donations', tags: ['Donations'], summary: 'Get donations for a campaign', parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donCamp() {}

    #[OA\Get(path: '/donations/fundraiser', tags: ['Donations'], summary: 'Get fundraiser recent donations', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donFund() {}

    #[OA\Get(path: '/donations/fundraiser-stats', tags: ['Donations'], summary: 'Get fundraiser donation stats for chart', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'period', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'Minggu'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donStats() {}

    #[OA\Get(path: '/donations/my', tags: ['Donations'], summary: 'Get my donations (Donor)', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donMy() {}

    #[OA\Get(path: '/donations/{donation}/check', tags: ['Donations'], summary: 'Manual check Xendit status', parameters: [new OA\Parameter(name: 'donation', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function donCheck() {}

    // ========================================================================
    // CAMPAIGN UPDATES & IMAGES
    // ========================================================================
    #[OA\Get(path: '/campaigns/{campaign}/updates', tags: ['Campaign Updates'], summary: 'List updates', parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function updIdx() {}

    #[OA\Post(path: '/campaigns/{campaign}/updates', tags: ['Campaign Updates'], summary: 'Create update', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function updStore() {}

    #[OA\Put(path: '/campaigns/{campaign}/updates/{update}', tags: ['Campaign Updates'], summary: 'Edit update', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'update', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function updUpdate() {}

    #[OA\Delete(path: '/campaigns/{campaign}/updates/{update}', tags: ['Campaign Updates'], summary: 'Delete update', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'update', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function updDel() {}

    #[OA\Get(path: '/campaigns/{campaign}/images', tags: ['Campaign Images'], summary: 'List images', parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function imgIdx() {}

    #[OA\Post(path: '/campaigns/{campaign}/images', tags: ['Campaign Images'], summary: 'Upload image', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function imgStore() {}

    #[OA\Delete(path: '/campaigns/{campaign}/images/{image}', tags: ['Campaign Images'], summary: 'Delete image', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function imgDel() {}

    // ========================================================================
    // DELETE REQUESTS
    // ========================================================================
    #[OA\Get(path: '/delete-requests', tags: ['Admin Delete Requests'], summary: 'List delete requests', security: [['sanctum' => []]], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function delReqIdx() {}

    #[OA\Post(path: '/campaigns/{campaign}/delete-request', tags: ['Fundraiser Delete Requests'], summary: 'Request campaign deletion', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'campaign', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function delReqStore() {}

    #[OA\Patch(path: '/delete-requests/{deleteRequest}/approve', tags: ['Admin Delete Requests'], summary: 'Approve delete', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'deleteRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function delReqApp() {}

    #[OA\Patch(path: '/delete-requests/{deleteRequest}/reject', tags: ['Admin Delete Requests'], summary: 'Reject delete', security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'deleteRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success')])]
    public function delReqRej() {}
}
