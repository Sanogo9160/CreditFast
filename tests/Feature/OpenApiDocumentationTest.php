<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    public function test_openapi_spec_is_generated_and_documents_core_endpoints(): void
    {
        $this->artisan('l5-swagger:generate')->assertSuccessful();

        $path = storage_path('api-docs/api-docs.json');
        $this->assertFileExists($path);

        /** @var array<string, mixed> $docs */
        $docs = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertStringStartsWith('3.0.', (string) $docs['openapi']);
        $this->assertSame('CreditFast API', $docs['info']['title']);

        $paths = $docs['paths'];
        $this->assertArrayHasKey('/api/auth/register', $paths);
        $this->assertArrayHasKey('/api/auth/client/login', $paths);
        $this->assertArrayHasKey('/api/auth/staff/login', $paths);
        $this->assertArrayNotHasKey('/api/auth/login', $paths);
        $this->assertArrayNotHasKey('/api/profile/activity', $paths);
        $this->assertArrayNotHasKey('/api/analyst/clients/{client}/kyc-documents/{kycDocument}/verify', $paths);
        $this->assertArrayNotHasKey('/api/analyst/guarantees/{guarantee}/verify', $paths);
        $this->assertArrayHasKey('/api/auth/password', $paths);
        $this->assertArrayHasKey('/api/profile', $paths);
        $this->assertArrayHasKey('/api/credit-requests', $paths);
        $this->assertArrayHasKey('/api/credit-requests/{creditRequest}/documents', $paths);
        $this->assertArrayHasKey('/api/credit-requests/{creditRequest}/score', $paths);
        $this->assertArrayHasKey('/api/agent/requests/{creditRequest}/request-complements', $paths);
        $this->assertArrayHasKey('/api/loans/{loan}/disburse', $paths);
        $this->assertArrayHasKey('/api/loans/{loan}/repayments/{repayment}/record', $paths);
        $this->assertArrayHasKey('/api/profile/activities', $paths);
        $this->assertArrayHasKey('/api/profile/activities/{activity}', $paths);
        $this->assertArrayHasKey('/api/profile/financial-profile', $paths);
        $this->assertArrayHasKey('/api/profile/kyc-documents', $paths);
        $this->assertArrayHasKey('/api/credit-requests/{creditRequest}/documents/{document}', $paths);
        $this->assertArrayHasKey('/api/credit-requests/{creditRequest}/guarantees/{guarantee}', $paths);
        $this->assertArrayHasKey('/api/notifications/{notification}', $paths);
        $this->assertArrayHasKey('/api/admin/users/{user}', $paths);
        $this->assertArrayHasKey('/api/admin/users/{user}/password', $paths);
        $this->assertArrayHasKey('/api/agent/clients', $paths);

        $this->assertArrayHasKey('get', $paths['/api/profile/activities']);
        $this->assertArrayHasKey('post', $paths['/api/profile/activities']);
        $this->assertArrayHasKey('put', $paths['/api/profile/activities/{activity}']);
        $this->assertArrayHasKey('delete', $paths['/api/profile/activities/{activity}']);
        $this->assertArrayHasKey('delete', $paths['/api/credit-requests/{creditRequest}']);
        $this->assertArrayHasKey('get', $paths['/api/admin/users/{user}']);
        $this->assertArrayHasKey('put', $paths['/api/admin/users/{user}']);
        $this->assertArrayHasKey('put', $paths['/api/auth/password']);
        $this->assertArrayHasKey('put', $paths['/api/admin/users/{user}/password']);
        $this->assertArrayHasKey('delete', $paths['/api/admin/users/{user}']);

        $this->assertArrayHasKey('sanctum', $docs['components']['securitySchemes']);
        $this->assertSame('bearer', $docs['components']['securitySchemes']['sanctum']['scheme']);
        $this->assertArrayHasKey('UploadCreditDocument', $docs['components']['requestBodies']);
        $this->assertArrayHasKey('multipart/form-data', $docs['components']['requestBodies']['UploadCreditDocument']['content']);

        $schemas = $docs['components']['schemas'];
        $this->assertSame(
            ['DRAFT', 'SUBMITTED', 'ANALYSIS', 'VERIFICATION_REQUIRED', 'CREDIT_REVIEW', 'COMMITTEE', 'APPROVED', 'REJECTED', 'DISBURSED'],
            $schemas['CreditRequestStatus']['enum']
        );
        $this->assertSame(['PENDING', 'VERIFIED', 'REJECTED'], $schemas['KycStatus']['enum']);
        $this->assertSame(['FAVORABLE', 'RESERVED', 'UNFAVORABLE'], $schemas['ScoringRecommendation']['enum']);
        $this->assertSame('#/components/schemas/CreditRequestStatus', $schemas['CreditRequest']['properties']['status']['$ref']);
        $this->assertSame('#/components/schemas/CreditDocumentType', $docs['components']['requestBodies']['UploadCreditDocument']['content']['multipart/form-data']['schema']['properties']['document_type']['$ref']);
        $this->assertArrayHasKey('/api/profile-photo', $paths);
        $this->assertArrayHasKey('get', $paths['/api/profile-photo']);
        $this->assertArrayHasKey('post', $paths['/api/profile-photo']);
        $this->assertArrayHasKey('put', $paths['/api/profile-photo']);
        $this->assertArrayHasKey('delete', $paths['/api/profile-photo']);
        $this->assertArrayHasKey('/api/users/{user}/photo/file', $paths);
        $this->assertArrayHasKey('/api/guarantees/{guarantee}/file', $paths);
        $this->assertArrayHasKey('StoreGuarantee', $docs['components']['requestBodies']);
        $this->assertArrayHasKey('application/json', $docs['components']['requestBodies']['StoreGuarantee']['content']);
        $this->assertArrayHasKey('multipart/form-data', $docs['components']['requestBodies']['StoreGuarantee']['content']);
        $this->assertArrayHasKey('file', $docs['components']['requestBodies']['StoreGuarantee']['content']['multipart/form-data']['schema']['properties']);
        $this->assertArrayHasKey('Guarantee', $docs['components']['schemas']);
        $this->assertStringContainsString('FAVORABLE', $paths['/api/analyst/requests/{creditRequest}/review']['post']['description']);
        $this->assertStringContainsString('APPROVED', $paths['/api/committee/requests/{creditRequest}/decide']['post']['description']);
    }

    public function test_swagger_ui_fetches_the_spec_from_a_same_origin_relative_url(): void
    {
        $html = $this->get('/api/documentation')
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->getContent();

        $this->assertStringContainsString('url: "/docs', $html);
        $this->assertStringContainsString('docExpansion : "list"', $html);
        $this->assertStringNotContainsString('https://creditfast.test/docs', $html);
        $this->assertStringNotContainsString('https://api.creditfast.ml/docs', $html);
    }
}
