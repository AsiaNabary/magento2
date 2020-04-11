<?php declare(strict_types=1);
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\ImportExport\Test\Unit\Helper;

use Magento\CatalogImportExport\Model\Import\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\Write;
use Magento\Framework\HTTP\Adapter\FileTransferFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\Timezone;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Magento\ImportExport\Helper\Data;
use Magento\ImportExport\Helper\Report;
use Magento\ImportExport\Model\Export\Adapter\CsvFactory;
use Magento\ImportExport\Model\History;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Config;
use Magento\ImportExport\Model\Import\Entity\Factory;
use Magento\MediaStorage\Model\File\UploaderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ReportTest extends TestCase
{
    /**
     * @var ObjectManagerHelper
     */
    protected $objectManagerHelper;

    /**
     * @var Context|MockObject
     */
    protected $context;

    /**
     * @var Timezone|MockObject
     */
    protected $timezone;

    /**
     * @var Filesystem|MockObject
     */
    protected $filesystem;

    /**
     * @var Write|MockObject
     */
    protected $varDirectory;

    /**
     * @var Report
     */
    protected $report;

    /**
     * @var Http|MockObject
     */
    private $requestMock;

    /**
     * Set up
     */
    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->requestMock = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->context->expects($this->any())->method('getRequest')->willReturn($this->requestMock);
        $this->timezone = $this->createPartialMock(
            Timezone::class,
            ['date', 'getConfigTimezone', 'diff', 'format']
        );
        $this->varDirectory = $this->createPartialMock(
            Write::class,
            ['getRelativePath', 'readFile', 'isFile', 'stat']
        );
        $this->filesystem = $this->createPartialMock(Filesystem::class, ['getDirectoryWrite']);
        $this->varDirectory->expects($this->any())->method('getRelativePath')->willReturn('path');
        $this->varDirectory->expects($this->any())->method('readFile')->willReturn('contents');
        $this->varDirectory->expects($this->any())->method('isFile')->willReturn(true);
        $this->varDirectory->expects($this->any())->method('stat')->willReturn(100);
        $this->filesystem->expects($this->any())->method('getDirectoryWrite')->willReturn($this->varDirectory);
        $this->objectManagerHelper = new ObjectManagerHelper($this);
        $this->report = $this->objectManagerHelper->getObject(
            Report::class,
            [
                'context' => $this->context,
                'timeZone' => $this->timezone,
                'filesystem' =>$this->filesystem
            ]
        );
    }

    /**
     * Test getExecutionTime()
     */
    public function testGetExecutionTime()
    {
        $startDate = '2000-01-01 01:01:01';
        $endDate = '2000-01-01 02:03:04';
        $executionTime = '01:02:03';

        $startDateMock = $this->createTestProxy(\DateTime::class, ['time' => $startDate]);
        $endDateMock = $this->createTestProxy(\DateTime::class, ['time' => $endDate]);
        $this->timezone->method('date')
            ->withConsecutive([$startDate], [])
            ->willReturnOnConsecutiveCalls($startDateMock, $endDateMock);

        $this->assertEquals($executionTime, $this->report->getExecutionTime($startDate));
    }

    /**
     * Test getExecutionTime()
     */
    public function testGetSummaryStats()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $filesystem = $this->createMock(Filesystem::class);
        $importExportData = $this->createMock(Data::class);
        $coreConfig = $this->createMock(ScopeConfigInterface::class);
        $importConfig = $this->createPartialMock(Config::class, ['getEntities']);
        $importConfig->expects($this->any())
            ->method('getEntities')
            ->willReturn(['catalog_product' => ['model' => 'catalog_product']]);
        $entityFactory = $this->createPartialMock(Factory::class, ['create']);
        $product = $this->createPartialMock(
            Product::class,
            ['getEntityTypeCode', 'setParameters']
        );
        $product->expects($this->any())->method('getEntityTypeCode')->willReturn('catalog_product');
        $product->expects($this->any())->method('setParameters')->willReturn('');
        $entityFactory->expects($this->any())->method('create')->willReturn($product);
        $importData = $this->createMock(\Magento\ImportExport\Model\ResourceModel\Import\Data::class);
        $csvFactory = $this->createMock(CsvFactory::class);
        $httpFactory = $this->createMock(FileTransferFactory::class);
        $uploaderFactory = $this->createMock(UploaderFactory::class);
        $behaviorFactory = $this->createMock(\Magento\ImportExport\Model\Source\Import\Behavior\Factory::class);
        $indexerRegistry = $this->createMock(IndexerRegistry::class);
        $importHistoryModel = $this->createMock(History::class);
        $localeDate = $this->createMock(\Magento\Framework\Stdlib\DateTime\DateTime::class);
        $import = new Import(
            $logger,
            $filesystem,
            $importExportData,
            $coreConfig,
            $importConfig,
            $entityFactory,
            $importData,
            $csvFactory,
            $httpFactory,
            $uploaderFactory,
            $behaviorFactory,
            $indexerRegistry,
            $importHistoryModel,
            $localeDate
        );
        $import->setData('entity', 'catalog_product');
        $message = $this->report->getSummaryStats($import);
        $this->assertInstanceOf(Phrase::class, $message);
    }

    /**
     * @dataProvider importFileExistsDataProvider
     * @param string $fileName
     * @return void
     */
    public function testImportFileExistsException($fileName)
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Filename has not permitted symbols in it');
        $this->report->importFileExists($fileName);
    }

    /**
     * Test importFileExists()
     */
    public function testImportFileExists()
    {
        $this->assertEquals($this->report->importFileExists('..file..name'), true);
    }

    /**
     * Dataprovider for testImportFileExistsException()
     *
     * @return array
     */
    public function importFileExistsDataProvider()
    {
        return [
            [
                'fileName' => 'some_folder/../another_folder',
            ],
            [
                'fileName' => 'some_folder\..\another_folder',
            ],
        ];
    }

    /**
     * Test importFileExists()
     */
    public function testGetReportOutput()
    {
        $this->assertEquals($this->report->getReportOutput('report'), 'contents');
    }

    /**
     * Test getReportSize()
     */
    public function testGetReportSize()
    {
        $result = $this->report->getReportSize('file');
        $this->assertNull($result);
    }

    /**
     * Test getDelimiter() take into consideration request param '_import_field_separator'.
     */
    public function testGetDelimiter()
    {
        $testDelimiter = 'some delimiter';
        $this->requestMock->expects($this->once())
            ->method('getParam')
            ->with($this->identicalTo(Import::FIELD_FIELD_SEPARATOR))
            ->willReturn($testDelimiter);
        $this->assertEquals(
            $testDelimiter,
            $this->report->getDelimiter()
        );
    }
}
