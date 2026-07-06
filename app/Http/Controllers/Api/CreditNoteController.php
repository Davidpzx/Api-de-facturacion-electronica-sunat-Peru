<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HandlesPdfGeneration;
use App\Http\Requests\IndexCreditNoteRequest;
use App\Http\Requests\StoreCreditNoteRequest;
use App\Models\CreditNote;
use App\Services\DocumentService;
use App\Services\FileService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CreditNoteController extends Controller
{
    use HandlesPdfGeneration;

    protected DocumentService $documentService;
    protected FileService $fileService;

    public function __construct(DocumentService $documentService, FileService $fileService)
    {
        $this->documentService = $documentService;
        $this->fileService = $fileService;
    }

    /**
     * Listar notas de crédito con filtros
     */
    public function index(IndexCreditNoteRequest $request): JsonResponse
    {
        try {
            $query = CreditNote::with(['company', 'branch', 'client']);
            $this->applyFilters($query, $request);

            $perPage = $request->get('per_page', 15);
            $creditNotes = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $creditNotes->items(),
                'pagination' => $this->getPaginationData($creditNotes)
            ]);

        } catch (Exception $e) {
            return $this->errorResponse('Error al listar notas de crédito', $e);
        }
    }

    /**
     * Crear nueva nota de crédito
     */
    public function store(StoreCreditNoteRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $creditNote = $this->documentService->createCreditNote($validated);

            return response()->json([
                'success' => true,
                'data' => $creditNote->load(['company', 'branch', 'client']),
                'message' => 'Nota de crédito creada correctamente'
            ], 201);

        } catch (Exception $e) {
            return $this->errorResponse('Error al crear la nota de crédito', $e);
        }
    }

    /**
     * Obtener nota de crédito específica
     */
    public function show(string $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $creditNote
            ]);
        } catch (Exception $e) {
            return $this->notFoundResponse('Nota de crédito no encontrada');
        }
    }

    /**
     * Enviar nota de crédito a SUNAT
     */
    public function sendToSunat(string $id): JsonResponse
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);

            if ($creditNote->estado_sunat === 'ACEPTADO') {
                return response()->json([
                    'success' => false,
                    'message' => 'La nota de crédito ya fue aceptada por SUNAT'
                ], 400);
            }

            $result = $this->documentService->sendToSunat($creditNote, 'credit_note');

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result['document']->load(['company', 'branch', 'client']),
                    'message' => 'Nota de crédito enviada exitosamente a SUNAT'
                ]);
            }

            return $this->handleSunatError($result);

        } catch (Exception $e) {
            return $this->errorResponse('Error interno al enviar a SUNAT', $e);
        }
    }

    /**
     * Descargar XML de nota de crédito
     */
    public function downloadXml(string $id): Response
    {
        try {
            $creditNote = CreditNote::findOrFail($id);

            if (!$this->fileService->fileExists($creditNote->xml_path)) {
                return $this->notFoundResponse('XML no encontrado');
            }

            return $this->fileService->downloadFile(
                $creditNote->xml_path,
                $creditNote->numero_completo . '.xml',
                ['Content-Type' => 'application/xml']
            );

        } catch (Exception $e) {
            return $this->errorResponse('Error al descargar XML', $e);
        }
    }

    /**
     * Descargar CDR de nota de crédito
     */
    public function downloadCdr(string $id): Response
    {
        try {
            $creditNote = CreditNote::findOrFail($id);

            if (!$this->fileService->fileExists($creditNote->cdr_path)) {
                return $this->notFoundResponse('CDR no encontrado');
            }

            return $this->fileService->downloadFile(
                $creditNote->cdr_path,
                'R-' . $creditNote->numero_completo . '.zip',
                ['Content-Type' => 'application/zip']
            );

        } catch (Exception $e) {
            return $this->errorResponse('Error al descargar CDR', $e);
        }
    }

    /**
     * Descargar PDF de nota de crédito
     */
    public function downloadPdf(string $id, Request $request): Response
    {
        try {
            $creditNote = CreditNote::findOrFail($id);
            return $this->downloadDocumentPdf($creditNote, $request);
        } catch (Exception $e) {
            return $this->errorResponse('Error al descargar PDF', $e);
        }
    }

    /**
     * Generar PDF de nota de crédito
     */
    public function generatePdf(string $id, Request $request): Response
    {
        try {
            $creditNote = CreditNote::with(['company', 'branch', 'client'])->findOrFail($id);
            return $this->generateDocumentPdf($creditNote, 'credit-note', $request);
        } catch (Exception $e) {
            return $this->errorResponse('Error al generar PDF', $e);
        }
    }

    /**
     * Aplicar filtros a la consulta
     */
    private function applyFilters($query, Request $request): void
    {
        $filters = [
            'company_id' => 'where',
            'branch_id' => 'where',
            'estado_sunat' => 'where',
            'tipo_doc_afectado' => 'where',
            'fecha_desde' => 'whereDate|>=',
            'fecha_hasta' => 'whereDate|<='
        ];

        foreach ($filters as $field => $operation) {
            if ($request->has($field)) {
                $parts = explode('|', $operation);
                $method = $parts[0];
                $operator = $parts[1] ?? null;

                if ($operator) {
                    $query->$method('fecha_emision', $operator, $request->$field);
                } else {
                    $query->$method($field, $request->$field);
                }
            }
        }
    }

    /**
     * Manejar error de SUNAT
     */
    private function handleSunatError(array $result): JsonResponse
    {
        $error = $result['error'];
        $errorCode = 'UNKNOWN';
        $errorMessage = 'Error desconocido';

        if (is_object($error)) {
            $errorCode = method_exists($error, 'getCode') ? $error->getCode() : ($error->code ?? $errorCode);
            $errorMessage = method_exists($error, 'getMessage') ? $error->getMessage() : ($error->message ?? $errorMessage);
        }

        return response()->json([
            'success' => false,
            'data' => $result['document'],
            'message' => 'Error al enviar a SUNAT: ' . $errorMessage,
            'error_code' => $errorCode
        ], 400);
    }

    /**
     * Obtener datos de paginación
     */
    private function getPaginationData($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * Respuesta de error estandarizada
     */
    private function errorResponse(string $message, Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message . ': ' . $e->getMessage()
        ], 500);
    }

    /**
     * Respuesta de no encontrado
     */
    private function notFoundResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 404);
    }
}
