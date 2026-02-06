<?php

namespace MatheusFS\Laravel\Insights\Services\Infrastructure;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * AWSCLIService - Execução de comandos AWS CLI
 *
 * Responsabilidade: Interação com AWS via CLI
 * Infrastructure layer (I/O)
 */
class AWSCLIService
{
    protected string $region;

    protected int $timeout;

    public function __construct(?string $region = null, int $timeout = 60)
    {
        $this->region = $region ?? config('insights.incident_correlation.aws_region', 'us-east-1');
        $this->timeout = $timeout;
    }

    /**
     * Executa comando AWS CLI
     *
     * @param  array  $args  Argumentos do comando (sem 'aws' prefix)
     * @return array Output parseado + exit code
     *
     * @throws ProcessFailedException
     */
    public function execute(array $args): array
    {
        $command = array_merge(['aws'], $args, ['--region', $this->region, '--output', 'json']);

        $process = new Process($command, null, null, null, $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        return [
            'success' => true,
            'data' => json_decode($output, true),
            'raw' => $output,
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * Cria IP Set no AWS WAF
     *
     * @param  array  $ipSetConfig  Configuração do IP Set
     * @return array Resposta da AWS com ID e ARN
     */
    public function createIpSet(array $ipSetConfig): array
    {
        $args = [
            'wafv2', 'create-ip-set',
            '--name', $ipSetConfig['Name'],
            '--scope', $ipSetConfig['Scope'],
            '--ip-address-version', $ipSetConfig['IPAddressVersion'],
            '--addresses', implode(' ', $ipSetConfig['Addresses']),
            '--description', $ipSetConfig['Description'],
        ];

        return $this->execute($args);
    }

    /**
     * Atualiza regras de Web ACL
     *
     * @param  string  $webAclId  ID do Web ACL
     * @param  string  $webAclName  Nome do Web ACL
     * @param  array  $rules  Array de regras
     * @param  string  $lockToken  Token de lock do Web ACL
     * @return array Resposta da AWS
     */
    public function updateWebAcl(
        string $webAclId,
        string $webAclName,
        array $rules,
        string $lockToken
    ): array {
        // Salvar regras em arquivo temporário (AWS CLI requer file:// para JSON complexo)
        $rulesFile = tempnam(sys_get_temp_dir(), 'waf_rules_');
        file_put_contents($rulesFile, json_encode($rules));

        try {
            $args = [
                'wafv2', 'update-web-acl',
                '--name', $webAclName,
                '--scope', 'CLOUDFRONT',
                '--id', $webAclId,
                '--lock-token', $lockToken,
                '--rules', 'file://'.$rulesFile,
            ];

            $result = $this->execute($args);

            return $result;
        } finally {
            unlink($rulesFile);
        }
    }

    /**
     * Obtém health status de target group do ELB
     *
     * @param  string  $targetGroupArn  ARN do target group
     * @return array Health status dos targets
     */
    public function getTargetHealth(string $targetGroupArn): array
    {
        $args = [
            'elbv2', 'describe-target-health',
            '--target-group-arn', $targetGroupArn,
        ];

        return $this->execute($args);
    }

    /**
     * Obtém status de instância RDS
     *
     * @param  string  $dbInstanceIdentifier  Identificador da instância
     * @return array Status da instância RDS
     */
    public function getRdsInstanceStatus(string $dbInstanceIdentifier): array
    {
        $args = [
            'rds', 'describe-db-instances',
            '--db-instance-identifier', $dbInstanceIdentifier,
        ];

        return $this->execute($args);
    }

    /**
     * Lista logs do ALB em período específico
     *
     * @param  string  $bucketName  Nome do bucket S3 com logs
     * @param  string  $prefix  Prefixo dos logs (ex: AWSLogs/123456789/elasticloadbalancing/us-east-1/2026/02/)
     * @return array Lista de arquivos de log
     */
    public function listAlbLogs(string $bucketName, string $prefix): array
    {
        $args = [
            's3', 'ls',
            "s3://{$bucketName}/{$prefix}",
            '--recursive',
        ];

        return $this->execute($args);
    }

    /**
     * Lista objetos no S3 usando s3api (JSON)
     *
     * @param  string  $bucketName  Nome do bucket
     * @param  string  $prefix  Prefixo
     * @return array Lista de objetos
     */
    public function listS3Objects(string $bucketName, string $prefix): array
    {
        $args = [
            's3api', 'list-objects-v2',
            '--bucket', $bucketName,
            '--prefix', $prefix,
        ];

        return $this->execute($args);
    }

    /**
     * Download de arquivo do S3
     *
     * @param  string  $bucketName  Nome do bucket
     * @param  string  $key  Key do objeto
     * @param  string  $localPath  Path local para salvar
     * @return array Resultado do download
     */
    public function downloadFromS3(string $bucketName, string $key, string $localPath): array
    {
        $args = [
            's3', 'cp',
            "s3://{$bucketName}/{$key}",
            $localPath,
        ];

        return $this->execute($args);
    }
}
