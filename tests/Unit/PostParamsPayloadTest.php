<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Requests\PostStoreRequest;
use App\Http\Requests\PostUpdateRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class PostParamsPayloadTest extends TestCase
{
    public function test_store_request_merges_nested_params_into_root(): void
    {
        $request = PostStoreRequest::create('/posts', 'POST', [
            'params' => [
                'title' => 'Título anidado',
                'excerpt' => 'Extracto',
                'content' => 'Contenido',
                'category_id' => 2,
                'image' => 'https://example.com/image.jpg',
                'tag_id' => ['dandadan', 'reseña'],
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('Título anidado', $request->input('title'));
        $this->assertSame('Extracto', $request->input('excerpt'));
        $this->assertSame(2, $request->input('category_id'));
        $this->assertSame(['dandadan', 'reseña'], $request->input('tag_id'));
    }

    public function test_store_request_keeps_flat_payload(): void
    {
        $request = PostStoreRequest::create('/posts', 'POST', [
            'title' => 'Título plano',
            'excerpt' => 'Extracto',
            'content' => 'Contenido',
            'category_id' => 2,
            'image' => 'https://example.com/image.jpg',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('Título plano', $request->input('title'));
        $this->assertNull($request->input('params'));
    }

    public function test_update_request_merges_nested_params_into_root(): void
    {
        $request = PostUpdateRequest::create('/posts/1', 'PUT', [
            'params' => [
                'title' => 'Update anidado',
                'content' => 'Contenido',
                'category_id' => 2,
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('Update anidado', $request->input('title'));
        $this->assertSame('Contenido', $request->input('content'));
    }

    public function test_store_rules_accept_string_tags_after_merge(): void
    {
        $request = PostStoreRequest::create('/posts', 'POST', [
            'params' => [
                'title' => 'Título',
                'excerpt' => 'Extracto corto',
                'content' => 'Contenido largo',
                'category_id' => 2,
                'image' => 'https://example.com/image.jpg',
                'tag_id' => ['dandadan', 'reseña'],
                'title_id' => null,
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        // category_id / title_id may fail exists:* without DB seed; assert required fields + tags shape pass
        $this->assertFalse($validator->errors()->has('title'));
        $this->assertFalse($validator->errors()->has('excerpt'));
        $this->assertFalse($validator->errors()->has('content'));
        $this->assertFalse($validator->errors()->has('image'));
        $this->assertFalse($validator->errors()->has('tag_id.0'));
        $this->assertFalse($validator->errors()->has('tag_id.1'));
    }

    private function invokePrepareForValidation(PostStoreRequest|PostUpdateRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->invoke($request);
    }
}
