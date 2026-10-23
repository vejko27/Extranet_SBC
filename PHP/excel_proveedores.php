<?php
// Lector mínimo de .xlsx (sin extensión zip): usa solo zlib y SimpleXML.

function xlsx_leer_zip(string $bin, int $maxBytes = 30000000): array
{
    $archivos = [];
    $eocd = strrpos($bin, "PK\x05\x06");
    if ($eocd === false || strlen($bin) < $eocd + 22) {
        throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
    }
    $total = unpack('v', substr($bin, $eocd + 10, 2))[1];
    $pos = unpack('V', substr($bin, $eocd + 16, 4))[1];
    $descomprimido = 0;
    for ($i = 0; $i < $total; $i++) {
        if (substr($bin, $pos, 4) !== "PK\x01\x02") {
            throw new RuntimeException('El archivo Excel está dañado.');
        }
        $c = [
            'metodo' => unpack('v', substr($bin, $pos + 10, 2))[1],
            'comp' => unpack('V', substr($bin, $pos + 20, 4))[1],
            'tam' => unpack('V', substr($bin, $pos + 24, 4))[1],
            'nom' => unpack('v', substr($bin, $pos + 28, 2))[1],
            'extra' => unpack('v', substr($bin, $pos + 30, 2))[1],
            'com' => unpack('v', substr($bin, $pos + 32, 2))[1],
        ];
        $off = unpack('V', substr($bin, $pos + 42, 4))[1];
        $nombre = substr($bin, $pos + 46, $c['nom']);
        $pos += 46 + $c['nom'] + $c['extra'] + $c['com'];

        if (!in_array($nombre, ['xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/sharedStrings.xml'], true)
            && !preg_match('#^xl/worksheets/[^/]+\.xml$#', $nombre)) {
            continue;
        }
        $descomprimido += $c['tam'];
        if ($descomprimido > $maxBytes) {
            throw new RuntimeException('El archivo Excel es demasiado grande.');
        }
        $nl = unpack('v', substr($bin, $off + 26, 2))[1];
        $el = unpack('v', substr($bin, $off + 28, 2))[1];
        $datos = substr($bin, $off + 30 + $nl + $el, $c['comp']);
        $archivos[$nombre] = $c['metodo'] === 8 ? gzinflate($datos) : $datos;
        if ($archivos[$nombre] === false) {
            throw new RuntimeException('No se pudo leer el contenido del Excel.');
        }
    }
    return $archivos;
}

function xlsx_xml(string $xml): SimpleXMLElement
{
    $xml = preg_replace('/\sxmlns(:\w+)?="[^"]*"/', '', $xml);
    $x = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    if (!$x) {
        throw new RuntimeException('El archivo Excel está dañado.');
    }
    return $x;
}

function xlsx_columna(string $ref): int
{
    preg_match('/^[A-Z]+/', $ref, $m);
    $n = 0;
    foreach (str_split($m[0] ?? 'A') as $ch) {
        $n = $n * 26 + ord($ch) - 64;
    }
    return $n;
}

// Devuelve las filas de la primera hoja como [nFila => [nCol => texto]]
function xlsx_filas(string $bin): array
{
    $f = xlsx_leer_zip($bin);
    if (!isset($f['xl/workbook.xml'])) {
        throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
    }

    $hoja = 'xl/worksheets/sheet1.xml';
    if (preg_match('/<sheet\b[^>]*\br:id="([^"]+)"/', $f['xl/workbook.xml'], $m) && isset($f['xl/_rels/workbook.xml.rels'])) {
        foreach (xlsx_xml($f['xl/_rels/workbook.xml.rels'])->Relationship as $r) {
            if ((string)$r['Id'] === $m[1]) {
                $t = ltrim((string)$r['Target'], '/');
                $hoja = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
            }
        }
    }
    if (!isset($f[$hoja])) {
        throw new RuntimeException('No se encontró la hoja del Excel.');
    }

    $compartidas = [];
    if (isset($f['xl/sharedStrings.xml'])) {
        foreach (xlsx_xml($f['xl/sharedStrings.xml'])->si as $si) {
            $compartidas[] = implode('', array_map('strval', $si->xpath('.//t')));
        }
    }

    $filas = [];
    foreach (xlsx_xml($f[$hoja])->sheetData->row as $row) {
        $nf = (int)$row['r'];
        foreach ($row->c as $c) {
            $t = (string)$c['t'];
            if ($t === 's') {
                $v = $compartidas[(int)$c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $v = implode('', array_map('strval', $c->xpath('.//t')));
            } else {
                $v = (string)$c->v;
                if ($t === '' && $v !== '' && is_numeric($v) && (stripos($v, 'E') !== false || str_contains($v, '.'))) {
                    $v = sprintf('%.0f', (float)$v);
                }
            }
            $filas[$nf][xlsx_columna((string)$c['r'])] = trim($v);
        }
    }
    return $filas;
}

function prov_norm(string $s): string
{
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    return preg_replace('/\s+/', ' ', $s);
}

// Lee el listado de proveedores. Devuelve ['filas' => [...], 'errores' => [...]]
function proveedores_desde_excel(string $bin): array
{
    $filas = xlsx_filas($bin);
    $encabezados = [
        'RAZON SOCIAL' => 'razon_social', 'RUC' => 'ruc', 'TIPO' => 'tipo', 'CERTIFICADO' => 'certificado',
        'MES' => 'mes', 'ACUERDO DE SEGURIDAD' => 'acuerdo_seguridad', 'DDJJ' => 'ddjj',
        'FILTROS APLICADOS' => 'filtros_aplicados', 'STATUS' => 'status', 'REGISTRADO EN EL SISTEMA' => 'registrado_sistema',
    ];

    $mapa = [];
    $filaCab = null;
    foreach ($filas as $n => $celdas) {
        $tmp = [];
        foreach ($celdas as $col => $v) {
            $k = prov_norm($v);
            if (isset($encabezados[$k])) {
                $tmp[$encabezados[$k]] = $col;
            }
        }
        if (isset($tmp['razon_social'], $tmp['ruc'])) {
            $mapa = $tmp;
            $filaCab = $n;
            break;
        }
    }
    if ($filaCab === null) {
        throw new RuntimeException('No se encontró la fila de encabezados (RAZÓN SOCIAL, RUC, ...).');
    }
    $faltan = array_diff(array_values($encabezados), array_keys($mapa));
    if ($faltan) {
        throw new RuntimeException('Faltan columnas en el Excel: ' . implode(', ', $faltan) . '.');
    }

    $meses = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SET', 'OCT', 'NOV', 'DIC'];
    $salida = [];
    $errores = [];
    foreach ($filas as $n => $celdas) {
        if ($n <= $filaCab) {
            continue;
        }
        $g = fn($campo) => $celdas[$mapa[$campo]] ?? '';
        $razon = trim($g('razon_social'));
        $ruc = preg_replace('/\s+/', '', $g('ruc'));
        if ($razon === '' && $ruc === '') {
            continue;
        }

        $mes = substr(prov_norm($g('mes')), 0, 3);
        $mes = $mes === 'SEP' ? 'SET' : $mes;
        $tri = function ($campo) use ($g) {
            $v = prov_norm($g($campo));
            return match ($v) { 'SI' => 'SI', 'NO', '' => 'NO', 'NO APLICA', 'N/A', 'NA' => 'NO APLICA', default => null };
        };
        $st = prov_norm($g('status'));
        $st = $st === '' ? 'PENDIENTE' : (str_starts_with($st, 'HOMOLOG') ? 'HOMOLOGADO' : ($st === 'PENDIENTE' ? $st : null));
        $reg = prov_norm($g('registrado_sistema'));
        $reg = $reg === '' ? 'NO' : (in_array($reg, ['SI', 'NO'], true) ? $reg : null);

        $p = [
            'razon_social' => $razon, 'ruc' => $ruc,
            'tipo' => prov_norm($g('tipo')), 'certificado' => prov_norm($g('certificado')),
            'mes' => $mes, 'acuerdo_seguridad' => $tri('acuerdo_seguridad'), 'ddjj' => $tri('ddjj'),
            'filtros_aplicados' => $tri('filtros_aplicados'), 'status' => $st, 'registrado_sistema' => $reg,
        ];

        $problema = null;
        if ($razon === '' || mb_strlen($razon) > 200) {
            $problema = 'razón social vacía o muy larga';
        } elseif (!preg_match('/^\d{11}$/', $ruc)) {
            $problema = 'RUC debe tener 11 dígitos';
        } elseif ($p['tipo'] === '' || mb_strlen($p['tipo']) > 40 || mb_strlen($p['certificado']) > 60) {
            $problema = 'tipo/certificado no válido';
        } elseif (!in_array($mes, $meses, true)) {
            $problema = 'mes no válido';
        } elseif (in_array(null, [$p['acuerdo_seguridad'], $p['ddjj'], $p['filtros_aplicados'], $p['status'], $p['registrado_sistema']], true)) {
            $problema = 'valor no válido en SI/NO/NO APLICA, status o registrado';
        }
        if ($problema) {
            $errores[] = "Fila $n: $problema";
        } else {
            $salida[] = $p;
        }
    }
    if (!$salida && !$errores) {
        $errores[] = 'El Excel no contiene proveedores debajo de los encabezados.';
    }
    return ['filas' => $salida, 'errores' => $errores];
}
