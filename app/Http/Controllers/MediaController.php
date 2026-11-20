<?php


namespace App\Http\Controllers;


use App\Helpers\MediaHelper;
use App\Models\Attachment;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Image;

use Illuminate\Http\File;

class MediaController
{
    private MediaService $_service;

    public function __construct(MediaService $service)
    {
        $this->_service = $service;
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files.*' => 'required|file|max:8192', //8MB = 8192
        ]);
        try {
            $files = request()->file('files');
            $data = [];
            $date = date('Ymd');
            $path = $this->createDirectory($date);
            foreach ($files as $file) {
                $fileName = getUuid() . '.' . $file->getClientOriginalExtension();
                $pathName = "/uploads/$date/$fileName";
//                $file->move($path, $fileName);
                if(in_array($file->getClientOriginalExtension(), ["jpg", "jpeg", "jfif", "pjpeg", "pjp", "png"])){
                    $img = Image::make($file->path());
                    $img->resize(800, 800, function ($constraint) {
                        $constraint->aspectRatio();
                    })->encode('jpg')->save($path.'/'.$fileName);
                }else{
                    $file->move($path, $fileName);
                }

                if (env('FILESYSTEM_DRIVER') == 'oss') {
                    $link  = $path . '/' . $fileName;
                    $pathOSS = 'hangve/' . $date;
                    Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

                    $pathName = '/' . $pathOSS . '/' . $fileName;
                    $full_path =  env('OSS_URL') . $pathName;
                    $data[] = ['name' => $fileName, 'path' => $pathName, "full_path" => $full_path, 'name_origin' => $file->getClientOriginalName()];

                    unlink($link);
                } else {
                    $data[] = [
                        'name' => $fileName,
                        'path' => $pathName,
                        'full_path' => MediaHelper::getURL($pathName)
                    ];
                }
            }
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            Log::error('Lỗi khi upload media: ' . $e);
            return resError($e->getMessage() . '---'.$e->getFile() . '-----'. $e->getLine());
        }
    }

    public function single(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:8192', //8MB = 8192
        ]);
        try {
            $file = request()->file('file');
            $date = date('Ymd');
            $path = $this->createDirectory($date);
            $fileName = getUuid() . '.' . $file->getClientOriginalExtension();
            if(in_array($file->getClientOriginalExtension(), ["jpg", "jpeg", "jfif", "pjpeg", "pjp", "png"])){
                $img = Image::make($file->path());
                $img->resize(800, 800, function ($constraint) {
                    $constraint->aspectRatio();
                })->encode('jpg')->save($path.'/'.$fileName);
            }else{
                $file->move($path, $fileName);
            }

            if (env('FILESYSTEM_DRIVER') == 'oss') {
                $link  = $path . '/' . $fileName;
                $pathOSS = 'hangve/' . $date;
                Storage::disk('oss')->putFileAs($pathOSS, new File($link), $fileName);

                $pathName = '/' . $pathOSS . '/' . $fileName;
                $full_path =  env('OSS_URL') . $pathName;
                $data = ['name' => $fileName, 'path' => $pathName, "full_path" => $full_path];

                unlink($link);
            } else {
                $pathName = "/uploads/$date/$fileName";
                $data = ['name' => $fileName, 'path' => $pathName, 'full_path' => MediaHelper::getURL($pathName)];
            }
            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            Log::error('Lỗi khi upload single medie: ' . $e);
            return resError();
        }
    }

    private function createDirectory(string $date): string
    {
        $path = public_path("storage/uploads/$date");
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    /**
     * @return JsonResponse
     */
    public function uploads(): JsonResponse
    {
        $basePath = $this->_service->makeDirectory();
        $files = request()->file('files');
        $attachments = [];
        foreach ($files as $file) {
            $originalFileName = $file->getClientOriginalName();
            $fileName = (getUuid()).'.'.$extension = $file->getClientOriginalExtension();
            $attachments[] = [
                // 'id' => getUuid(),
                'path' => date('Ymd').'/'.$fileName,
                'name' => $originalFileName,
                'extension' => $extension,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'storage' => "uploads",
                'created_at' => now()
            ];
            $file->move($basePath, $fileName);
        }
        Attachment::query()->insert($attachments);
        return resSuccessWithinData($attachments);
    }

    /**
     * @param  Attachment  $attachment
     * @return \never|StreamedResponse
     */
    public function getFileId(Attachment $attachment)
    {
        $storage = Storage::disk('uploads');
        /** @var Attachment $attachment */
        if ($attachment && $storage->exists($attachment->path)) {
            return $storage->download($attachment->path, $attachment->name);
        }
        return abort(Response::HTTP_NOT_FOUND, 'File không tồn tại');
    }

    /**
     * @param  Request  $request
     * @return JsonResponse
     */
    public function singleFile(Request $request): JsonResponse
    {
        $attachment = $this->_service->singleFile($request->file('file'));

        return resSuccessWithinData($attachment);
    }
}
