<?php

namespace Tests\Unit;

use App\Models\CloudProviderCredential;
use App\Models\EnterpriseLicense;
use App\Models\Organization;
use App\Models\TerraformDeployment;
use App\Models\WhiteLabelConfig;
use PHPUnit\Framework\TestCase;

class EnterpriseModelsTest extends TestCase
{
    public function test_organization_hierarchy_methods()
    {
        $org = new Organization([
            'hierarchy_type' => 'master_branch',
            'hierarchy_level' => 1,
        ]);

        $this->assertTrue($org->isMasterBranch());
        $this->assertFalse($org->isTopBranch());
        $this->assertFalse($org->isSubUser());
        $this->assertFalse($org->isEndUser());
    }

    public function test_enterprise_license_feature_checking()
    {
        $license = new EnterpriseLicense([
            'features' => ['terraform_integration', 'white_label', 'api_access'],
            'license_type' => 'subscription',
            'license_tier' => 'professional',
            'status' => 'active',
        ]);

        $this->assertTrue($license->hasFeature('terraform_integration'));
        $this->assertTrue($license->hasFeature('white_label'));
        $this->assertFalse($license->hasFeature('payment_processing'));

        $this->assertTrue($license->hasAnyFeature(['terraform_integration', 'payment_processing']));
        $this->assertFalse($license->hasAllFeatures(['terraform_integration', 'payment_processing']));

        $this->assertTrue($license->isProfessional());
        $this->assertTrue($license->isSubscription());
        $this->assertFalse($license->isTrial());
    }

    public function test_enterprise_license_validation()
    {
        $license = new EnterpriseLicense([
            'status' => 'active',
            // Test without expires_at to avoid database connection issues in unit tests
        ]);

        // Test status checks
        $this->assertFalse($license->isSuspended());
        $this->assertFalse($license->isRevoked());

        // Test status changes
        $license->status = 'suspended';
        $this->assertTrue($license->isSuspended());

        $license->status = 'revoked';
        $this->assertTrue($license->isRevoked());
    }

    public function test_enterprise_license_domain_authorization()
    {
        $license = new EnterpriseLicense([
            'authorized_domains' => ['example.com', '*.subdomain.com'],
        ]);

        $this->assertTrue($license->isDomainAuthorized('example.com'));
        $this->assertTrue($license->isDomainAuthorized('test.subdomain.com'));
        $this->assertFalse($license->isDomainAuthorized('unauthorized.com'));
    }

    public function test_white_label_config_theme_methods()
    {
        $config = new WhiteLabelConfig([
            'platform_name' => 'Custom Platform',
            'theme_config' => [
                'primary_color' => '#ff0000',
                'secondary_color' => '#00ff00',
            ],
        ]);

        $this->assertEquals('Custom Platform', $config->getPlatformName());
        $this->assertEquals('#ff0000', $config->getThemeVariable('primary_color'));
        $this->assertEquals('#ffffff', $config->getThemeVariable('background_color', '#ffffff')); // default value

        $config->setThemeVariable('accent_color', '#0000ff');
        $this->assertEquals('#0000ff', $config->getThemeVariable('accent_color'));
    }

    public function test_white_label_config_domain_management()
    {
        $config = new WhiteLabelConfig([
            'custom_domains' => ['example.com'],
        ]);

        $this->assertTrue($config->hasCustomDomain('example.com'));
        $this->assertFalse($config->hasCustomDomain('test.com'));

        $config->addCustomDomain('test.com');
        $this->assertTrue($config->hasCustomDomain('test.com'));

        $config->removeCustomDomain('example.com');
        $this->assertFalse($config->hasCustomDomain('example.com'));
    }

    public function test_cloud_provider_credential_validation()
    {
        $credential = new CloudProviderCredential([
            'provider_name' => 'aws',
        ]);

        $this->assertEquals('Amazon Web Services', $credential->getProviderDisplayName());
        $this->assertTrue($credential->isProviderSupported());
        $this->assertEquals(['access_key_id', 'secret_access_key'], $credential->getRequiredCredentialKeys());
        $this->assertEquals(['session_token', 'region'], $credential->getOptionalCredentialKeys());
    }

    public function test_cloud_provider_credential_aws_validation()
    {
        $credential = new CloudProviderCredential([
            'provider_name' => 'aws',
        ]);

        // Test valid AWS credentials
        $validCredentials = [
            'access_key_id' => 'AKIAIOSFODNN7EXAMPLE',
            'secret_access_key' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        ];

        $this->expectNotToPerformAssertions();
        $credential->validateCredentialsForProvider($validCredentials);
    }

    public function test_cloud_provider_credential_aws_validation_failure()
    {
        $credential = new CloudProviderCredential([
            'provider_name' => 'aws',
        ]);

        // Test invalid AWS credentials
        $invalidCredentials = [
            'access_key_id' => 'invalid',
            'secret_access_key' => 'invalid',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $credential->validateCredentialsForProvider($invalidCredentials);
    }

    public function test_terraform_deployment_status_methods()
    {
        $deployment = new TerraformDeployment([
            'status' => TerraformDeployment::STATUS_PROVISIONING,
        ]);

        $this->assertTrue($deployment->isProvisioning());
        $this->assertTrue($deployment->isInProgress());
        $this->assertFalse($deployment->isCompleted());
        $this->assertFalse($deployment->isFinished());

        // Test status change without database interaction
        $deployment->status = TerraformDeployment::STATUS_COMPLETED;
        $this->assertEquals(TerraformDeployment::STATUS_COMPLETED, $deployment->status);
    }

    public function test_terraform_deployment_configuration_methods()
    {
        $deployment = new TerraformDeployment([
            'deployment_config' => [
                'instance_type' => 't3.micro',
                'region' => 'us-east-1',
                'disk_size' => 20,
            ],
        ]);

        $this->assertEquals('t3.micro', $deployment->getInstanceType());
        $this->assertEquals('us-east-1', $deployment->getRegion());
        $this->assertEquals(20, $deployment->getDiskSize());

        $deployment->setConfigValue('instance_type', 't3.small');
        $this->assertEquals('t3.small', $deployment->getConfigValue('instance_type'));
    }
}
