<?php

namespace WebHappens\Prismic\Tests;

use stdClass;
use Prismic\Api;
use Mockery as m;
use WebHappens\Prismic\Query;
use WebHappens\Prismic\Slice;
use WebHappens\Prismic\Prismic;
use WebHappens\Prismic\Document;
use Illuminate\Support\Collection;
use WebHappens\Prismic\Tests\Stubs\SliceAStub;
use WebHappens\Prismic\Tests\Stubs\SliceBStub;
use WebHappens\Prismic\Tests\Stubs\DocumentAStub;
use WebHappens\Prismic\Tests\Stubs\DocumentBStub;

class DocumentTest extends TestCase
{
    public function test_make()
    {
        $this->assertInstanceOf(DocumentAStub::class, DocumentAStub::make());
    }

    public function test_get_type()
    {
        $this->assertEquals('document_a', DocumentAStub::getType());
    }

    public function test_get_global_field_keys()
    {
        $this->assertEquals(DocumentAStub::getGlobalFieldKeys(), [
            'id', 'uid', 'type', 'href', 'tags', 'first_publication_date',
            'last_publication_date', 'lang', 'alternate_languages',
        ]);
    }

    public function test_resolve_class_from_type()
    {
        Prismic::documents([DocumentAStub::class, DocumentBStub::class]);
        $this->assertEquals(DocumentAStub::class, Document::resolveClassFromType('document_a'));
        $this->assertEquals(DocumentBStub::class, Document::resolveClassFromType('document_b'));
        Prismic::$documents = [];
    }

    public function test_new_hydrated_instance()
    {
        $this->assertNull(Document::newHydratedInstance(new stdClass));

        $resultStub = (object) [
            'type' => 'document_a',
            'id' => '1',
            'data' => [
                'foo' => 'bar',
                'uri' => 'document-a', // Maps to `url` then casts to url
            ],
        ];

        Prismic::documents([DocumentAStub::class]);
        $document = Document::newHydratedInstance($resultStub);
        $this->assertInstanceOf(DocumentAStub::class, $document);
        $this->assertEquals('1', $document->id);
        $this->assertEquals('bar', $document->foo);
        $this->assertEquals(url('document-a'), $document->url);
        Prismic::$documents = [];
    }

    public function test_all()
    {
        $rawStub = (object) [
            'total_pages' => 1,
            'results' => [
                (object) ['id' => '1', 'type' => 'document_a'],
                (object) ['id' => '2', 'type' => 'document_a'],
            ],
        ];

        $expectedPredicates = Query::make()->where('type', 'document_a')->toPredicates();
        $expectedOptions = ['pageSize' => 100, 'page' => 1];

        $api = m::mock(Api::class);
        $api->shouldReceive('query')->once()->with($expectedPredicates, $expectedOptions)->andReturn($rawStub);
        $this->swap(Api::class, $api);

        Prismic::documents([DocumentAStub::class]);
        $all = DocumentAStub::all();
        $this->assertCount(2, $all);
        $this->assertContainsOnlyInstancesOf(DocumentAStub::class, $all);
        Prismic::$documents = [];
    }

    public function test_is_linkable()
    {
        $document = DocumentAStub::make();
        $this->assertFalse($document->isLinkable());
        $document->title = 'foo';
        $document->url = 'https://example.org';
        $this->assertTrue($document->isLinkable());
    }

    public function test_get_slices()
    {
        Prismic::slices([SliceAStub::class, SliceBStub::class]);

        $document = DocumentAStub::make();
        $document->body = [
            ['slice_type' => 'slice_a'],
            ['slice_type' => 'slice_b'],
        ];

        $allSlices = $document->getSlices();
        $this->assertInstanceOf(Collection::class, $allSlices);
        $this->assertCount(2, $allSlices);
        $this->assertContainsOnlyInstancesOf(Slice::class, $allSlices);

        $sliceA = $document->getSlices('slice_a');
        $this->assertInstanceOf(Collection::class, $sliceA);
        $this->assertCount(1, $sliceA);
        $this->assertContainsOnlyInstancesOf(SliceAStub::class, $sliceA);

        Prismic::$slices = [];
    }

    public function test_new_query()
    {
        $query = DocumentAStub::make()->newQuery();
        $predicates = $query->toPredicates();
        $this->assertInstanceOf(Query::class, $query);
        $this->assertCount(1, $predicates);
        $this->assertEquals('[:d = at(document.type, "document_a")]', $predicates[0]->q());
    }

    public function test_get_maps()
    {
        $this->assertEquals([
            'href' => 'api_id',
            'first_publication_date' => 'first_published',
            'last_publication_date' => 'last_published',
            'lang' => 'language',
            'uri' => 'url',
        ], DocumentAStub::make()->getMaps());
    }

    public function test_get_casts()
    {
        $this->assertEquals([
            'first_published' => 'date',
            'last_published' => 'date',
            'url' => 'url',
        ], DocumentAStub::make()->getCasts());
    }

    public function test_call_forwarding_to_query()
    {
        $this->assertInstanceOf(Query::class, DocumentAStub::make()->where('foo', 'bar'));
        $this->assertInstanceOf(Query::class, DocumentAStub::where('foo', 'bar'));
    }
}
