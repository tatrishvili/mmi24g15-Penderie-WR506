<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Validation;

/**
 * storePhoto() and deleteFile() are private pure-ish I/O helpers of the photo
 * pipeline; the happy path is covered by the API tests, the failure modes of
 * the filesystem are triggered here with a real temporary directory (and one
 * unwritable path), still without a container.
 */
final class ItemServicePhotoTest extends TestCase
{
    private const PNG_DATA_URL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private string $uploadDir;

    protected function setUp(): void
    {
        $this->uploadDir = sys_get_temp_dir().'/penderie-photo-'.bin2hex(random_bytes(4));
        mkdir($this->uploadDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadDir.'/items')) {
            if (is_link($this->uploadDir.'/items')) {
                unlink($this->uploadDir.'/items');
            } else {
                array_map('unlink', glob($this->uploadDir.'/items/*') ?: []);
                rmdir($this->uploadDir.'/items');
            }
        }
        if (is_dir($this->uploadDir)) {
            rmdir($this->uploadDir);
        }
    }

    public function testStoredPhotoLandsInTheUploadDirectory(): void
    {
        $service = $this->service($this->uploadDir);

        $path = $this->invoke($service, 'storePhoto', self::PNG_DATA_URL, 1);

        self::assertStringStartsWith('items/', $path);
        self::assertFileExists($this->uploadDir.'/items/'.substr($path, \strlen('items/')));
    }

    public function testStorageDirectoryCreationFailuresAreReported(): void
    {
        // /etc/hostname is a FILE: mkdir below it can never succeed.
        $service = $this->service('/etc/hostname/blocked');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('storage is unavailable');
        $this->invoke($service, 'storePhoto', self::PNG_DATA_URL, 1);
    }

    public function testFileWriteFailuresAreReported(): void
    {
        // The items directory "exists" (symlink to a procfs directory) but
        // procfs refuses file creation: file_put_contents returns false.
        symlink('/proc/self/task', $this->uploadDir.'/items');
        $service = $this->service($this->uploadDir);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('storage is unavailable');
        $this->invoke($service, 'storePhoto', self::PNG_DATA_URL, 1);
    }

    public function testDeleteFileRemovesPhotosOnlyInsideItemsDirectory(): void
    {
        $service = $this->service($this->uploadDir);
        mkdir($this->uploadDir.'/items', 0755, true);

        $foreign = $this->uploadDir.'/keep-me.txt';
        file_put_contents($foreign, 'x');
        $this->invoke($service, 'deleteFile', 'uploads/keep-me.txt');
        self::assertFileExists($foreign, 'Foreign paths must never be deleted.');

        file_put_contents($this->uploadDir.'/items/photo.jpg', 'x');
        $this->invoke($service, 'deleteFile', 'items/photo.jpg');
        self::assertFileDoesNotExist($this->uploadDir.'/items/photo.jpg');
    }

    private function service(string $uploadDir): ItemService
    {
        return new ItemService(
            $this->createStub(EntityManagerInterface::class),
            Validation::createValidator(),
            $uploadDir,
            new \App\Service\CollectionSharing($this->createStub(EntityManagerInterface::class)),
        );
    }

    private function invoke(ItemService $service, string $method, mixed ...$args): mixed
    {
        $reflection = new \ReflectionMethod(ItemService::class, $method);

        return $reflection->invoke($service, ...$args);
    }
}
