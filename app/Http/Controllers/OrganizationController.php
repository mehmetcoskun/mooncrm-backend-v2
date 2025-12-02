<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    private const MAX_FILE_SIZE = 10485760;

    public function index()
    {
        if (Gate::none(['organization_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return Organization::orderBy('id', 'desc')->get();
    }

    public function store(Request $request)
    {
        if (Gate::none(['organization_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->only(['name', 'code', 'trial_ends_at', 'license_ends_at', 'is_active']);

        if (!empty($data['trial_ends_at']) && !empty($data['license_ends_at'])) {
            return response()->json(['message' => 'Deneme süresi ve lisans süresi aynı anda tanımlanamaz. Lütfen sadece birini seçin.'], 400);
        }

        if ($request->hasFile('logo')) {
            $logoUrl = $this->uploadFile($request->file('logo'));
            if ($logoUrl) {
                $data['logo'] = $logoUrl;
            } else {
                return response()->json(['message' => 'Logo yüklenirken hata oluştu. Desteklenen formatlar: JPEG, PNG (Maksimum 10MB)'], 400);
            }
        }

        return Organization::create($data);
    }

    public function show(Organization $organization)
    {
        return response()->json($organization);
    }

    public function update(Request $request, Organization $organization)
    {
        if (Gate::none(['organization_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->only(['name', 'code', 'trial_ends_at', 'license_ends_at', 'is_active']);

        if (!empty($data['trial_ends_at']) && !empty($data['license_ends_at'])) {
            return response()->json(['message' => 'Deneme süresi ve lisans süresi aynı anda tanımlanamaz. Lütfen sadece birini seçin.'], 400);
        }

        if ($request->has('remove_logo') && $request->remove_logo === 'true') {
            if ($organization->logo) {
                $this->deleteFile($organization->logo);
            }
            $data['logo'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($organization->logo) {
                $this->deleteFile($organization->logo);
            }

            $logoUrl = $this->uploadFile($request->file('logo'));
            if ($logoUrl) {
                $data['logo'] = $logoUrl;
            } else {
                return response()->json(['message' => 'Logo yüklenirken hata oluştu. Desteklenen formatlar: JPEG, PNG (Maksimum 10MB)'], 400);
            }
        }

        $organization->update($data);

        return response()->json($organization);
    }

    public function destroy(Organization $organization)
    {
        if (Gate::none(['organization_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        if ($organization->logo) {
            $this->deleteFile($organization->logo);
        }

        return $organization->delete();
    }

    private function uploadFile($file)
    {
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

            if ($file->getSize() > self::MAX_FILE_SIZE) {
                return null;
            }

            $allowedTypes = ['image/jpeg', 'image/png'];
            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return null;
            }

            $extension = $file->getClientOriginalExtension();
            $filename = 'organizations/' . Str::uuid() . '.' . $extension;

            $s3Client->putObject([
                'Bucket' => env('CLOUDFLARE_R2_BUCKET'),
                'Key' => $filename,
                'Body' => fopen($file->getPathname(), 'r'),
                'ACL' => 'public-read',
                'ContentType' => $file->getMimeType()
            ]);

            return $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function deleteFile($filePath)
    {
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
                'Key' => $filePath
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }
}
