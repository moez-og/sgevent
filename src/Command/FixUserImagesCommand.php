<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:users:fix-images',
    description: 'Detects broken local user images and replaces them with a fallback path.',
)]
class FixUserImagesCommand extends Command
{
    public function __construct(private readonly Connection $connection, #[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show broken image rows without updating the database.')
            ->addOption('default', null, InputOption::VALUE_REQUIRED, 'Fallback image path to store in DB.', 'theme/images/logo.png');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $defaultPath = trim((string) $input->getOption('default'));

        if ($defaultPath === '') {
            $io->error('The fallback path cannot be empty.');
            return Command::INVALID;
        }

        $rows = $this->connection->fetchAllAssociative('SELECT id, prenom, nom, imageUrl FROM user ORDER BY id ASC');
        if (count($rows) === 0) {
            $io->success('No users found.');
            return Command::SUCCESS;
        }

        $broken = [];
        foreach ($rows as $row) {
            $imagePath = (string) ($row['imageUrl'] ?? '');
            if (!$this->isBrokenLocalImage($imagePath)) {
                continue;
            }

            $broken[] = [
                'id' => (int) $row['id'],
                'prenom' => (string) ($row['prenom'] ?? ''),
                'nom' => (string) ($row['nom'] ?? ''),
                'imageUrl' => $imagePath,
            ];
        }

        if (count($broken) === 0) {
            $io->success('No broken local user image references found.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Detected %d broken local user image reference(s).', count($broken)));
        $io->table(['id', 'prenom', 'nom', 'imageUrl'], array_map(
            static fn (array $item): array => [
                (string) $item['id'],
                $item['prenom'],
                $item['nom'],
                $item['imageUrl'],
            ],
            $broken
        ));

        if ($dryRun) {
            $io->note('Dry run mode: no DB update executed.');
            return Command::SUCCESS;
        }

        $updated = 0;
        foreach ($broken as $item) {
            $updated += $this->connection->update('user', ['imageUrl' => $defaultPath], ['id' => $item['id']]);
        }

        $io->success(sprintf('Updated %d user row(s) to fallback image: %s', $updated, $defaultPath));
        return Command::SUCCESS;
    }

    private function isBrokenLocalImage(string $imagePath): bool
    {
        $path = trim($imagePath);
        if ($path === '') {
            return true;
        }

        if (preg_match('#^https?://#i', $path)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);
        $normalized = ltrim($normalized, '/');

        if (str_starts_with($normalized, 'public/')) {
            $normalized = substr($normalized, 7);
        }

        if (!str_contains($normalized, '/')) {
            $normalized = 'uploads/users/'.$normalized;
        }

        if (!str_starts_with($normalized, 'uploads/') && !str_starts_with($normalized, 'theme/')) {
            if (str_contains($normalized, 'uploads/')) {
                $normalized = 'uploads/'.explode('uploads/', $normalized, 2)[1];
            }
        }

        $fullPath = $this->projectDir.'/public/'.$normalized;

        return !is_file($fullPath);
    }
}
