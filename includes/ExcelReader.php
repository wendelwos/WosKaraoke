<?php
/**
 * Classe para leitura e cache do arquivo Excel de repertório
 * 
 * @author WosKaraoke
 * @version 1.0
 */

declare(strict_types=1);

namespace WosKaraoke;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ExcelReader
{
    private string $excelPath;
    private string $cachePath;
    private array $songs = [];

    public function __construct(string $excelPath, string $cachePath)
    {
        $this->excelPath = $excelPath;
        $this->cachePath = $cachePath;
    }

    /**
     * Obtém todas as músicas (do cache ou lendo o Excel)
     */
    public function getSongs(): array
    {
        if (!empty($this->songs)) {
            return $this->songs;
        }

        // Verificar se cache existe e está atualizado
        if ($this->isCacheValid()) {
            $this->songs = $this->loadFromCache();
            return $this->songs;
        }

        // Ler do Excel e criar cache
        $this->songs = $this->readFromExcel();
        $this->saveToCache($this->songs);

        return $this->songs;
    }

    /**
     * Verifica se o cache é válido (existe e é mais recente que o Excel)
     */
    private function isCacheValid(): bool
    {
        if (!file_exists($this->cachePath)) {
            return false;
        }

        if (!file_exists($this->excelPath)) {
            return false;
        }

        return filemtime($this->cachePath) > filemtime($this->excelPath);
    }

    /**
     * Carrega dados do cache
     */
    private function loadFromCache(): array
    {
        $content = file_get_contents($this->cachePath);
        return json_decode($content, true) ?? [];
    }

    /**
     * Salva dados no cache
     */
    private function saveToCache(array $songs): void
    {
        $dir = dirname($this->cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->cachePath,
            json_encode($songs, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Lê dados do arquivo Excel
     */
    private function readFromExcel(): array
    {
        if (!file_exists($this->excelPath)) {
            return [];
        }

        $spreadsheet = IOFactory::load($this->excelPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $songs = [];

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();

        // Detectar colunas dinamicamente pela primeira linha (cabeçalho)
        $headers = [];
        foreach ($worksheet->getRowIterator(1, 1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $col = 0;
            foreach ($cellIterator as $cell) {
                $value = strtolower(trim((string) $cell->getValue()));
                $headers[$col] = $value;
                $col++;
                if ($col > 10) break; // Limite de colunas
            }
        }

        // Mapear colunas (tentar detectar automaticamente)
        $codeCol = $this->findColumn($headers, ['codigo', 'code', 'cod', 'id', 'num', 'numero']);
        $artistCol = $this->findColumn($headers, ['cantor', 'artista', 'artist', 'interprete', 'singer']);
        $titleCol = $this->findColumn($headers, ['musica', 'titulo', 'title', 'nome', 'music', 'song']);
        $lyricsCol = $this->findColumn($headers, ['letra', 'lyrics', 'trecho', 'inicio', 'verso']);

        // Ler dados a partir da linha 2
        for ($row = 2; $row <= $highestRow; $row++) {
            $code = trim((string) $worksheet->getCellByColumnAndRow($codeCol + 1, $row)->getValue());
            $artist = trim((string) $worksheet->getCellByColumnAndRow($artistCol + 1, $row)->getValue());
            $title = trim((string) $worksheet->getCellByColumnAndRow($titleCol + 1, $row)->getValue());
            $lyrics = $lyricsCol !== null 
                ? trim((string) $worksheet->getCellByColumnAndRow($lyricsCol + 1, $row)->getValue())
                : '';

            if (empty($code) && empty($artist) && empty($title)) {
                continue;
            }

            $songs[] = [
                'code' => $code,
                'artist' => $artist,
                'title' => $title,
                'lyrics' => $lyrics,
                'search' => $this->normalizeForSearch("$code $artist $title $lyrics")
            ];
        }

        return $songs;
    }

    /**
     * Encontra o índice da coluna baseado em possíveis nomes
     */
    private function findColumn(array $headers, array $possibleNames): int
    {
        foreach ($headers as $index => $header) {
            foreach ($possibleNames as $name) {
                if (strpos($header, $name) !== false) {
                    return $index;
                }
            }
        }
        // Retorna índice padrão se não encontrar
        return match(true) {
            in_array('codigo', $possibleNames) => 0,
            in_array('cantor', $possibleNames) => 1,
            in_array('musica', $possibleNames) => 2,
            in_array('letra', $possibleNames) => null, // Coluna de letra é opcional
            default => 0
        };
    }

    /**
     * Busca músicas por termo
     */
    public function search(string $query, int $limit = 50, int $offset = 0): array
    {
        $songs = $this->getSongs();
        $query = mb_strtolower(trim($query), 'UTF-8');

        if (empty($query)) {
            return [
                'songs' => array_slice($songs, $offset, $limit),
                'total' => count($songs)
            ];
        }

        // Normalizar query (remover acentos)
        $queryNormalized = $this->normalizeForSearch($query);

        // Busca por múltiplos termos (AND)
        $terms = preg_split('/\s+/', $queryNormalized);
        
        $results = [];
        
        foreach ($songs as $song) {
            // Verifica se todos os termos estão presentes
            $match = true;
            foreach ($terms as $term) {
                if (strpos($song['search'], $term) === false) {
                    $match = false;
                    break;
                }
            }
            
            if (!$match) continue;
            
            // Calcula score de relevância
            $score = 0;
            
            // Normaliza campos para comparação
            $titleNorm = $this->normalizeForSearch($song['title'] ?? '');
            $artistNorm = $this->normalizeForSearch($song['artist'] ?? '');
            $codeNorm = $this->normalizeForSearch($song['code'] ?? '');
            $lyricsNorm = $this->normalizeForSearch($song['lyrics'] ?? '');
            
            // Código exato = máxima prioridade
            if ($codeNorm === $queryNormalized) {
                $score += 1000;
            }
            
            // Título exato = altíssima prioridade
            if ($titleNorm === $queryNormalized) {
                $score += 500;
            }
            // Título começa com a query
            elseif (strpos($titleNorm, $queryNormalized) === 0) {
                $score += 300;
            }
            // Título contém a query
            elseif (strpos($titleNorm, $queryNormalized) !== false) {
                $score += 200;
            }
            
            // Artista exato
            if ($artistNorm === $queryNormalized) {
                $score += 150;
            }
            // Artista começa com a query
            elseif (strpos($artistNorm, $queryNormalized) === 0) {
                $score += 100;
            }
            // Artista contém a query
            elseif (strpos($artistNorm, $queryNormalized) !== false) {
                $score += 50;
            }
            
            // Match na letra (menor prioridade)
            if (strpos($lyricsNorm, $queryNormalized) !== false) {
                $score += 10;
            }
            
            // Bonus para termos individuais no título
            foreach ($terms as $term) {
                if (strpos($titleNorm, $term) !== false) {
                    $score += 5;
                }
            }
            
            $song['_score'] = $score;
            $results[] = $song;
        }

        // Ordenar por score (maior primeiro), depois por título
        usort($results, function($a, $b) {
            // Primeiro por score
            if ($a['_score'] !== $b['_score']) {
                return $b['_score'] - $a['_score'];
            }
            // Depois por título alfabeticamente
            return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
        });

        // Remove score antes de retornar
        $results = array_map(function($song) {
            unset($song['_score']);
            return $song;
        }, $results);

        return [
            'songs' => array_slice($results, $offset, $limit),
            'total' => count($results)
        ];
    }

    /**
     * Normaliza texto para busca (minúsculas + remove acentos)
     */
    private function normalizeForSearch(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        
        // Mapa de caracteres acentuados para seus equivalentes
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
            'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
            'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c', 'Ñ' => 'n'
        ];
        
        return strtr($text, $accents);
    }
}
