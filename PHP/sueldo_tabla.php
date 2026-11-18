<div class="tabla-wrap sueldo-tabla-wrap">
    <table class="sueldo-tabla">
        <thead>
            <tr>
                <th>Documento</th><th>Apellidos y nombres</th><th>Puesto</th><th>Sede</th><th>Fecha de ingreso</th>
                <?php foreach (SUELDO_CAMPOS as $etiqueta): ?><th><?= sueldoEscapar($etiqueta) ?></th><?php endforeach; ?>
                <?php if ($esMantenimiento && $puedeEditarSueldo): ?><th>Acción</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$trabajadores): ?>
                <tr><td colspan="<?= 5 + count(SUELDO_CAMPOS) + ($esMantenimiento && $puedeEditarSueldo ? 1 : 0) ?>">No hay trabajadores activos registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($trabajadores as $trabajador):
                $documento = $trabajador['dni'] ?: ($trabajador['carnet_extranjeria'] ?: $trabajador['pasaporte']);
            ?>
                <tr>
                    <td data-label="Documento"><?= sueldoEscapar($documento) ?></td>
                    <td data-label="Apellidos y nombres"><?= sueldoEscapar(sueldoNombre($trabajador)) ?></td>
                    <td data-label="Puesto"><?= sueldoEscapar($trabajador['puesto_trabajo']) ?></td>
                    <td data-label="Sede"><?= sueldoEscapar($trabajador['sede_labora']) ?></td>
                    <td data-label="Fecha de ingreso"><?= sueldoEscapar($trabajador['fecha_ingreso'] ? date('d/m/Y', strtotime($trabajador['fecha_ingreso'])) : '') ?></td>
                    <?php foreach (SUELDO_CAMPOS as $campo => $etiqueta):
                        $valor = $trabajador[$campo];
                        if ($campo === 'prestamo' && $trabajador['prestamo_estado'] === 'no_aplica') {
                            $valor = 'No aplica';
                        }
                        if ($campo === 'fecha_pago' && $valor) {
                            $valor = date('d/m/Y', strtotime($valor));
                        }
                    ?>
                        <td data-label="<?= sueldoEscapar($etiqueta) ?>"><?= $valor === null || $valor === '' ? '—' : sueldoEscapar($valor) ?></td>
                    <?php endforeach; ?>
                    <?php if ($esMantenimiento && $puedeEditarSueldo): ?>
                        <td data-label="Acción"><a class="btn btn-sec" href="sueldo_mantenimiento.php?periodo=<?= sueldoEscapar($periodo) ?>&amp;personal=<?= (int)$trabajador['id'] ?>#editar-sueldo">Editar</a></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
