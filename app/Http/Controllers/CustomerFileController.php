<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerFile;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CustomerFileController extends Controller
{
    private const MAX_FILE_SIZE = 104857600;

    public function index(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_FileAccess']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        $customerFiles = $customer->files;

        return response()->json($customerFiles);
    }

    public function store(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_FileUpload']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        try {
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
                'credentials' => [
                    'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
                    'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
                ],
            ]);

            if (!$request->hasFile('files')) {
                return response()->json(['error' => 'Dosya yüklenmedi'], 400);
            }

            $files = $request->file('files');
            
            // Tek dosya ise array'e çevir
            if (!is_array($files)) {
                $files = [$files];
            }
            
            $uploadedFiles = [];

            foreach ($files as $file) {
                if (!$file || $file->getSize() > self::MAX_FILE_SIZE) {
                    continue;
                }

                $extension = $file->getClientOriginalExtension();
                $key = 'customers/' . $customer->id . '_' . Str::uuid() . '.' . $extension;

                $s3Client->putObject([
                    'Bucket' => env('CLOUDFLARE_R2_BUCKET'),
                    'Key' => $key,
                    'Body' => fopen($file->getPathname(), 'r'),
                    'ACL' => 'public-read'
                ]);

                $customerFile = CustomerFile::create([
                    'customer_id' => $customer->id,
                    'title' => $file->getClientOriginalName(),
                    'key' => $key,
                ]);

                $uploadedFiles[] = $customerFile;
            }

            return response()->json([
                'message' => 'Dosyalar başarıyla yüklendi',
                'files' => $uploadedFiles,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Dosya yükleme işlemi başarısız'], 500);
        }
    }

    public function update(Request $request, Customer $customer, CustomerFile $customerFile)
    {
        if (Gate::none(['customer_FileUpload']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        try {
            $customerFile->update([
                'title' => $request->input('title'),
            ]);

            return response()->json([
                'message' => 'Dosya adı güncellendi',
                'file' => $customerFile,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Dosya güncelleme işlemi başarısız'], 500);
        }
    }

    public function destroy(Request $request, Customer $customer, CustomerFile $customerFile)
    {
        if (Gate::none(['customer_FileDelete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        try {
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => env('CLOUDFLARE_R2_ENDPOINT'),
                'credentials' => [
                    'key' => env('CLOUDFLARE_R2_ACCESS_KEY_ID'),
                    'secret' => env('CLOUDFLARE_R2_SECRET_ACCESS_KEY'),
                ],
            ]);

            $s3Client->deleteObject([
                'Bucket' => env('CLOUDFLARE_R2_BUCKET'),
                'Key' => $customerFile->key
            ]);

            $customerFile->delete();

            return response()->json([
                'message' => 'Dosya başarıyla silindi'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Dosya silme işlemi başarısız',
            ], 500);
        }
    }
}
