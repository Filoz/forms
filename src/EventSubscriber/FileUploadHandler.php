<?php

namespace Bolt\BoltForms\EventSubscriber;

use Bolt\BoltForms\Event\PostSubmitEvent;
use Bolt\BoltForms\FormHelper;
use Bolt\Common\Str;
use Cocur\Slugify\Slugify;
use Sirius\Upload\Handler;
use Sirius\Upload\Result\File;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tightenco\Collect\Support\Collection;
use Webmozart\PathUtil\Path;

class FileUploadHandler implements EventSubscriberInterface
{
    /** @var string */
    private $projectDir;

    /** @var FormHelper */
    private $helper;

    public function __construct(string $projectDir, FormHelper $helper)
    {
        $this->helper = $helper;
        $this->projectDir = $projectDir;
    }

    public function handleEvent(PostSubmitEvent  $event): void
    {
        $form = $event->getForm();
        $formConfig = $event->getFormConfig();

        $fields = $form->all();
        foreach($fields as $field) {
            $file = $field->getData();
            if($file instanceof UploadedFile) {
                $fieldConfig = $formConfig->get('fields')[$field->getName()];

                $filename = $this->getFilename($field->getName(), $file, $form, $formConfig);
                $path = $fieldConfig['directory'] ?? '/uploads/';
                Str::ensureStartsWith($path, DIRECTORY_SEPARATOR);
                $files = $this->uploadFiles($filename, $file, $path);

                if (isset($fieldConfig['attach']) && $fieldConfig['attach']) {
                    $event->addAttachments($files);
                }
            }
        }
    }

    private function getFilename(string $fieldname, UploadedFile $file, Form $form, Collection $formConfig): string
    {
        $filenameFormat = $formConfig->get('fields')[$fieldname]['file_format'] ?? 'Uploaded file'.uniqid();
        $filename = $this->helper->get($filenameFormat, $form, ['filename' => $file->getClientOriginalName()]);

        if (! $filename) {
            $filename = uniqid();
        }

        return $filename;
    }

    private function uploadFiles(string $filename, UploadedFile $file, string $path = ''): array
    {
        $uploadPath = $this->projectDir.$path;
        $uploadHandler = new Handler($uploadPath, [
            Handler::OPTION_AUTOCONFIRM => true,
            Handler::OPTION_OVERWRITE => false,
        ]);

        $uploadHandler->setPrefix(substr(md5(time()), 0, 8) . '_' . $filename);

        $uploadHandler->setSanitizerCallback(function ($name) {
            return $this->sanitiseFilename($name);
        });

        /** @var File $processed */
        $processed = $uploadHandler->process($file);

        $result = [];
        if ($processed->isValid()) {
            $processed->confirm();
            $result[] = $uploadPath . $processed->__get('name');
        }

        // Very ugly. But it works when later someone uses Request::createFromGlobals();
        $_FILES = [];

        return $result;
    }

    private function sanitiseFilename(string $filename): string
    {
        $extensionSlug = new Slugify(['regexp' => '/([^a-z0-9]|-)+/']);
        $filenameSlug = new Slugify(['lowercase' => false]);

        $extension = $extensionSlug->slugify(Path::getExtension($filename));
        $filename = $filenameSlug->slugify(Path::getFilenameWithoutExtension($filename));

        return $filename . '.' . $extension;
    }

    public static function getSubscribedEvents()
    {
        return [
            'boltforms.post_submit' => ['handleEvent', 40],
        ];
    }
}
