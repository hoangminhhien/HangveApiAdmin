<?php

namespace App\Services;

use App\Helpers\MediaHelper;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File;

class MediaService
{
    /**
     * @param $file
     * @return Builder|Model|JsonResponse
     */
    public function singleFile($file)
    {
        $basePath = $this->makeDirectory();
        if (!$file) {
            return resError(trans('system.files_not_found'));
        }
        $fileName = getUuid() . '.' . $file->getClientOriginalExtension();
        $attachment = Attachment::query()->create(
            [
                'path' => '/' . date('Ymd') . '/' . $fileName,
                'name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'storage' => 'uploads',
            ]
        );
        $file->move($basePath, $fileName);

        return $attachment;
    }

    public function singleFileV2($file)
    {
        $date = date('Ymd');
        $path = $this->makeDirectory();
        $fileName = getUuid() . '.' . $file->getClientOriginalExtension();
        if(in_array($file->getClientOriginalExtension(), ["jpg", "jpeg", "jfif", "pjpeg", "pjp", "png"])){
            $img = Image::make($file->path());
            $img->resize(600, 600, function ($constraint) {
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

        return $data;
    }

    /**
     * @return string
     */
    public function makeDirectory(): string
    {
        $basePath = public_path('storage/uploads/' . date('Ymd'));

        //      is_dir only return true is the passed string is actually a directory.
        //      It will return false if it is a file.
        //      file_exists returns true if the passed string is a file OR a directory.

        if (!is_dir($basePath)) {
            mkdir($basePath);
        }
        return $basePath;
    }

    /**
     * Uplaod ảnh lên server của Hangve Admin
     * @param array $files
     * @return JsonResponse
     */
    public function upload(array $files)
    {
        try {
            $data = [];
            $date = date('Ymd');

            $path = public_path("storage/uploads/$date");
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            foreach ($files as $file) {
                $fileName = getUuid() . '.' . $file->getClientOriginalExtension();
                $file->move($path, $fileName);
                $pathName = "/uploads/$date/$fileName";
                $data[] = ['name' => $fileName, 'path' => $pathName, "full_path" => MediaHelper::getURL($pathName)];
            }

            return resSuccessWithinData($data);
        } catch (\Exception $e) {
            Log::error($e);
            return resError();
        }
    }
}
