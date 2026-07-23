<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Utils;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use App\Services\Ai\Agents\Utils\UserMessageAttachments;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Storage\Interfaces\FileInterface;
use App\Services\Storage\Values\FileType;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(UserMessageAttachments::class)]
class UserMessageAttachmentsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSettings(bool $canHandleFiles = true): AiModelSettings
    {
        return AiModelSettings::fromArray([
            WellKnownModelSettings::FILE_UPLOAD => $canHandleFiles,
        ]);
    }

    private function makeIoMethods(bool $hasImage = true, bool $hasText = true): AiModelIoMethods
    {
        $methods = [];
        if ($hasImage) {
            $methods[] = 'image';
        }
        if ($hasText) {
            $methods[] = 'text';
        }
        return AiModelIoMethods::fromArray($methods);
    }

    private function makeModel(
        bool $canHandleFiles = true,
        bool $hasImage = true,
        bool $hasText = true
    ): AiModel
    {
        $model = new AiModel();
        $model->settings = $this->makeSettings($canHandleFiles);
        $model->input = $this->makeIoMethods($hasImage, $hasText);
        return $model;
    }

    private function makeAdapter(bool $supportsAttachment = true): ProviderAdapterInterface&MockObject
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('supportsFileAsAttachment')->willReturn($supportsAttachment);
        return $adapter;
    }

    private function makeContext(
        bool $canHandleFiles = true,
        bool $hasImage = true,
        bool $hasText = true,
        bool $supportsAttachment = true
    ): AgentRequestContext
    {
        $proxy = new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->makeAdapter($supportsAttachment),
            driver: $this->createMock(Provider::class),
        );

        return new AgentRequestContext(
            provider: $proxy,
            model: $this->makeModel($canHandleFiles, $hasImage, $hasText),
            modelParameters: new AiModelParameters(),
        );
    }

    private function makeFile(
        string   $filename = 'file.txt',
        string   $mimeType = 'text/plain',
        string   $content  = 'hello world',
        int      $size     = 11,
        FileType $fileType = FileType::PLAIN_TEXT
    ): FileInterface&MockObject
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('getOriginalFilename')->willReturn($filename);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getContent')->willReturn($content);
        $file->method('getSize')->willReturn($size);
        $file->method('getFileType')->willReturn($fileType);
        return $file;
    }

    private function makeUserMessage(string $content = 'The user question'): UserMessage
    {
        return new UserMessage(content: $content);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);
        static::assertInstanceOf(UserMessageAttachments::class, $sut);
    }

    public function testItConstructsWithExplicitMeta(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context, new MessageMetaBlocks());
        static::assertInstanceOf(UserMessageAttachments::class, $sut);
    }

    // =========================================================================
    // register — model does not support files
    // =========================================================================

    public function testItSkipsFileWhenModelCannotHandleFiles(): void
    {
        $context = $this->makeContext(canHandleFiles: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile();

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('[HKI_META_SKIPPED_ATTACHMENTS]', $message->content);
        static::assertStringContainsString('file.txt', $message->content);
    }

    public function testItSkipsFileWithReasonWhenModelCannotHandleFiles(): void
    {
        $context = $this->makeContext(canHandleFiles: false);
        $sut = new UserMessageAttachments($context);
        $sut->register($this->makeFile());

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('Attachments not allowed for this model', $message->content);
    }

    public function testItReturnsSelfFromRegisterWhenModelCannotHandleFiles(): void
    {
        $context = $this->makeContext(canHandleFiles: false);
        $sut = new UserMessageAttachments($context);
        static::assertSame($sut, $sut->register($this->makeFile()));
    }

    // =========================================================================
    // register — image file
    // =========================================================================

    public function testItAddsImageAsReferencedAttachment(): void
    {
        $context = $this->makeContext(hasImage: true, supportsAttachment: true);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('photo.jpg', 'image/jpeg', 'binarydata', 10, FileType::IMAGE);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertCount(1, $message->attachments);
    }

    public function testItSkipsImageWhenModelHasNoImageInput(): void
    {
        $context = $this->makeContext(hasImage: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('photo.jpg', 'image/jpeg', 'binarydata', 10, FileType::IMAGE);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('[HKI_META_SKIPPED_ATTACHMENTS]', $message->content);
        static::assertStringContainsString('Model does not support image input', $message->content);
    }

    public function testItSkipsImageWhenAdapterDoesNotSupportAttachment(): void
    {
        $context = $this->makeContext(hasImage: true, supportsAttachment: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('photo.jpg', 'image/jpeg', 'binarydata', 10, FileType::IMAGE);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('HAWKI provider adapter does not support', $message->content);
    }

    // =========================================================================
    // register — plain text file
    // =========================================================================

    public function testItAddsPlainTextAsReferencedAttachmentWhenAdapterSupports(): void
    {
        $context = $this->makeContext(hasText: true, supportsAttachment: true);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('readme.txt', 'text/plain', 'Some text content', 17, FileType::PLAIN_TEXT);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertCount(1, $message->attachments);
    }

    public function testItInlinesPlainTextWhenAdapterDoesNotSupportAttachment(): void
    {
        $context = $this->makeContext(hasText: true, supportsAttachment: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('readme.txt', 'text/plain', 'Some text content', 17, FileType::PLAIN_TEXT);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertCount(0, $message->attachments);
        static::assertStringContainsString('[HKI_META_INLINED_ATTACHMENTS]', $message->content);
        static::assertStringContainsString('readme.txt', $message->content);
        static::assertStringContainsString('Some text content', $message->content);
    }

    public function testItEscapesBackticksInInlinedContent(): void
    {
        $context = $this->makeContext(hasText: true, supportsAttachment: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('code.txt', 'text/plain', 'Use ```code``` blocks', 21, FileType::PLAIN_TEXT);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('\`\`\`code\`\`\`', $message->content);
    }

    public function testItSkipsPlainTextWhenModelHasNoTextInput(): void
    {
        $context = $this->makeContext(hasText: false);
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('readme.txt', 'text/plain', 'content', 7, FileType::PLAIN_TEXT);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('[HKI_META_SKIPPED_ATTACHMENTS]', $message->content);
        static::assertStringContainsString('Model does not support text input', $message->content);
    }

    // =========================================================================
    // register — unsupported file type (non-StoredFile)
    // =========================================================================

    public function testItSkipsFileWithUnsupportedType(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);
        $file = $this->makeFile('video.mp4', 'video/mp4', 'binarydata', 100, FileType::VIDEO);

        $sut->register($file);

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('[HKI_META_SKIPPED_ATTACHMENTS]', $message->content);
        static::assertStringContainsString('Unsupported file type: video', $message->content);
    }

    // =========================================================================
    // addError
    // =========================================================================

    public function testItAddsErrorToContextMarkdown(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);
        $sut->addError('Something went wrong while uploading.');

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('[HKI_META_ATTACHMENTS_WITH_ERRORS]', $message->content);
        static::assertStringContainsString('Something went wrong while uploading.', $message->content);
    }

    public function testItDeduplicatesIdenticalErrors(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);
        $sut->addError('Duplicate error');
        $sut->addError('Duplicate error');

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertSame(
            1,
            substr_count($message->content, 'Duplicate error')
        );
    }

    public function testItReturnsSelfFromAddError(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);
        static::assertSame($sut, $sut->addError('error'));
    }

    // =========================================================================
    // apply — no context markdown when nothing registered
    // =========================================================================

    public function testItDoesNotPrependContextMarkdownWhenNothingRegistered(): void
    {
        $context = $this->makeContext();
        $sut = new UserMessageAttachments($context);

        $original = 'The user question';
        $message = $this->makeUserMessage($original);
        $sut->apply($message);

        static::assertSame($original, $message->content);
    }

    // =========================================================================
    // apply — context markdown prepended with double newline
    // =========================================================================

    public function testItPrependsContextMarkdownBeforeMessageContent(): void
    {
        $context = $this->makeContext(canHandleFiles: false);
        $sut = new UserMessageAttachments($context);
        $sut->register($this->makeFile());

        $message = $this->makeUserMessage('The user question');
        $sut->apply($message);

        static::assertStringEndsWith("\n\nThe user question", $message->content);
    }

    // =========================================================================
    // apply — referenced attachments added to message
    // =========================================================================

    public function testItAddsMultipleReferencedAttachmentsToMessage(): void
    {
        $context = $this->makeContext(hasText: true, supportsAttachment: true);
        $sut = new UserMessageAttachments($context);
        $sut->register($this->makeFile('a.txt', 'text/plain', 'content a', 9, FileType::PLAIN_TEXT));
        $sut->register($this->makeFile('b.txt', 'text/plain', 'content b', 9, FileType::PLAIN_TEXT));

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertCount(2, $message->attachments);
    }

    // =========================================================================
    // Skipped attachment message format
    // =========================================================================

    public function testItIncludesMimeTypeAndSizeInSkippedMessage(): void
    {
        $context = $this->makeContext(canHandleFiles: false);
        $sut = new UserMessageAttachments($context);
        $sut->register($this->makeFile('report.pdf', 'application/pdf', '', 2048, FileType::PDF));

        $message = $this->makeUserMessage();
        $sut->apply($message);

        static::assertStringContainsString('application/pdf', $message->content);
        static::assertStringContainsString('2048 bytes', $message->content);
    }
}
