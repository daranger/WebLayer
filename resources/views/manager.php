<div class="page-card file-manager-card">
    <div class="toolbar-container" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
        <div class="toolbar-left" style="gap: 8px; flex-wrap: wrap;">
            <div style="position: relative; display: flex; align-items: center; gap: 8px;">
                <button class="btn btn-secondary btn-sm" id="quickSearchBtn" title="Поиск" style="border-radius: 50%; padding: 8px 10px;" onclick="toggleQuickSearch()">
                    <i class="fa-solid fa-search"></i>
                </button>
                <div id="quickSearchContainer" style="display: none; align-items: center; gap: 4px;">
                    <input type="text" id="quickSearchInput" placeholder="Быстрый поиск..." oninput="filterFiles()" style="padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); width: 200px; font-size: 13px;">
                    <button class="btn btn-secondary btn-sm" onclick="toggleQuickSearch(false)" style="padding: 4px 8px; border: none; background: transparent;"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            
            <div style="position: relative;">
                <button class="btn btn-primary btn-sm context-trigger-btn" data-dropdown="createDropdown" style="border-radius: 20px; padding: 6px 16px;">
                    <i class="fa-solid fa-plus"></i> Создать
                </button>
                <div class="context-dropdown" id="createDropdown" style="left: 0; right: auto; top: 35px;">
                    <a href="#" class="dropdown-item" onclick="showCreateModal('file'); return false;"><i class="fa-regular fa-file"></i> Файл</a>
                    <a href="#" class="dropdown-item" onclick="showCreateModal('folder'); return false;"><i class="fa-solid fa-folder"></i> Каталог</a>
                </div>
            </div>


            <button class="btn btn-secondary btn-sm disabled toolbar-del-btn" disabled>
                <i class="fa-solid fa-trash-can"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled" disabled onclick="downloadSelected()">
                <i class="fa-solid fa-download"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled toolbar-copy-btn" disabled onclick="openCopyModal()">
                <i class="fa-regular fa-copy"></i>
            </button>
            <button class="btn btn-secondary btn-sm disabled toolbar-edit-btn" disabled onclick="editSelected()">
                <i class="fa-solid fa-pen-to-square"></i>
            </button>
            
            <span style="color: var(--border-color);">|</span>
            
            <button class="btn btn-secondary btn-sm disabled toolbar-attr-btn" disabled>
                <i class="fa-solid fa-tag"></i> Имя и атрибуты
            </button>
            <button class="btn btn-secondary btn-sm" onclick="toggleUploadPanel(true)">
                <i class="fa-solid fa-upload"></i> Загрузить
            </button>
            <button class="btn btn-secondary btn-sm" onclick="openGlobalSearchModal()">
                <i class="fa-solid fa-magnifying-glass"></i> Поиск
            </button>
            <div style="position: relative;">
                <button class="btn btn-secondary btn-sm disabled context-trigger-btn" id="toolbarArchiveBtn" data-dropdown="archiveDropdown" disabled>
                    <i class="fa-solid fa-file-zipper"></i> Архив <i class="fa-solid fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
                </button>
                <div class="context-dropdown" id="archiveDropdown" style="left: 0; right: auto; top: 35px;">
                    <a href="#" class="dropdown-item" id="createArchiveBtn" onclick="openCreateArchiveModal(); return false;"><i class="fa-solid fa-file-zipper"></i> Создать архив</a>
                    <a href="#" class="dropdown-item" id="extractArchiveBtn" onclick="extractSelectedArchive(); return false;"><i class="fa-solid fa-box-open"></i> Извлечь</a>
                </div>
            </div>

        </div>
        <div class="toolbar-right">
        </div>
    </div>
    
    <!-- Path bar -->
    <div style="padding: 12px 32px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card);">
        <div style="display: flex; align-items: center; gap: 16px; font-size: 14px;">
            <?php 
                $parentPath = dirname($current_path);
                if ($parentPath === $current_path) $parentPath = $current_path; // Root
            ?>
            <a href="?path=<?= urlencode($parentPath) ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 14px; text-decoration: none;">
                <i class="fa-solid fa-arrow-up"></i>
            </a>
            <div id="breadcrumbDisplay" style="display: flex; gap: 4px; font-weight: 500; align-items: center;">

                <?php
                    $pathParts = array_filter(explode(DIRECTORY_SEPARATOR, str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $current_path)));
                    $buildPath = '';
                    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                        echo '<a href="?path=/" style="text-decoration: none; background: rgba(99, 102, 241, 0.1); padding: 4px 8px; border-radius: 4px; color: var(--primary-color);">/</a>';
                    }
                    $isFirst = true;
                    foreach ($pathParts as $part) {
//                        if (!$isFirst || strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
//                            echo '<span style="color: var(--text-muted);"> / </span>';
//                        }
                        $isFirst = false;
                        $buildPath .= (empty($buildPath) && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' ? '/' : '') . $part . DIRECTORY_SEPARATOR;
                        $cleanPath = rtrim($buildPath, DIRECTORY_SEPARATOR);
                        echo '<a href="?path=' . urlencode($cleanPath) . '" style="text-decoration: none; background: rgba(99, 102, 241, 0.1); padding: 4px 8px; border-radius: 4px; color: var(--primary-color);">' . htmlspecialchars($part) . '/</a>';
                    }
                ?>
            </div>
            <div id="breadcrumbEdit" style="display: none; align-items: center; width: 100%;">
                <input type="text" id="pathEditInput" value="<?= htmlspecialchars($current_path, ENT_QUOTES) ?>" style="flex: 1; min-width: 450px; padding: 6px 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);">
            </div>
        </div>
        
        <div id="pathToolsNormal" style="display: flex; gap: 12px; color: var(--text-muted); font-size: 16px; align-items: center;">
            <i class="fa-regular fa-file" style="cursor: pointer;" title="Новый файл" onclick="showCreateModal('file')"></i>
            <i class="fa-solid fa-pen" style="cursor: pointer;" title="Редактировать путь" onclick="togglePathEdit(true)"></i>
            
        </div>
        
        <div id="pathToolsEdit" style="display: none; gap: 8px; color: var(--text-muted); font-size: 14px; align-items: center;">
            <button onclick="togglePathEdit(false)" style="background: none; border: none; cursor: pointer; color: var(--text-muted);"><i class="fa-solid fa-xmark" style="font-size: 16px;"></i></button>
            <button onclick="submitPathEdit()" style="background: none; border: none; cursor: pointer; color: var(--primary-color);"><i class="fa-solid fa-arrow-right" style="font-size: 16px;"></i></button>
            <button onclick="togglePathEdit(false)" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-weight: 500;">Отмена</button>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div style="padding: 20px 32px; color: var(--danger); background: rgba(244, 63, 94, 0.1);">
            <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive" style="padding-top: 0;">
        <table class="data-table">
            <thead>
            <?php 
                $sort = $_GET['sort'] ?? 'name';
                $order = $_GET['order'] ?? 'asc';
                $nextOrder = $order === 'asc' ? 'desc' : 'asc';
                
                function sortIcon($col, $currentSort, $currentOrder) {
                    if ($col !== $currentSort) return '';
                    return $currentOrder === 'asc' ? '<i class="fa-solid fa-arrow-down-a-z" style="margin-left: 4px;"></i>' : '<i class="fa-solid fa-arrow-up-z-a" style="margin-left: 4px;"></i>';
                }
            ?>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAllFiles" onchange="toggleAllFiles(this)"></th>
                <th style="color: var(--primary-color); cursor: pointer;" onclick="window.location.href='?path=<?= urlencode($current_path) ?>&sort=name&order=<?= $sort === 'name' ? $nextOrder : 'asc' ?>'">Имя <?= sortIcon('name', $sort, $order) ?></th>
                <th style="color: var(--primary-color); cursor: pointer;" onclick="window.location.href='?path=<?= urlencode($current_path) ?>&sort=size&order=<?= $sort === 'size' ? $nextOrder : 'asc' ?>'">Размер <?= sortIcon('size', $sort, $order) ?></th>
                <th style="color: var(--primary-color);">Права</th>
                <th style="color: var(--primary-color);">Владелец</th>
                <th style="color: var(--primary-color);">Группа</th>
                <th style="color: var(--primary-color); cursor: pointer;" onclick="window.location.href='?path=<?= urlencode($current_path) ?>&sort=date&order=<?= $sort === 'date' ? $nextOrder : 'asc' ?>'">Дата изменения <?= sortIcon('date', $sort, $order) ?></th>
                <th style="width: 50px; text-align: right;"><i class="fa-solid fa-chevron-down text-muted" style="font-size: 12px;"></i></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($files) && empty($error)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 40px;">
                        Папка пуста
                    </td>
                </tr>
            <?php elseif (!empty($files)): ?>
                <?php foreach ($files as $file): ?>
                    <?php 
                        $newPath = rtrim($current_path, '/\\') . DIRECTORY_SEPARATOR . $file['name'];
                        // Fix for Windows root e.g. C:
                        if (preg_match('/^[a-zA-Z]:$/', rtrim($current_path, '/\\'))) {
                            $newPath = rtrim($current_path, '/\\') . DIRECTORY_SEPARATOR . $file['name'];
                        }
                    ?>
                    <tr data-file-id="<?= $file['id'] ?>" class="file-row">
                        <td style="text-align: center;"><input type="checkbox" class="file-checkbox" value="<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>" data-size="<?= htmlspecialchars($file['raw_size'] ?? 0) ?>" onchange="updateToolbarState()"></td>
                        <td style="font-weight: 500;">
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($file['is_dir']): ?>
                                        <i class="fa-solid fa-folder" style="color: var(--primary-color);"></i>
                                        <a href="?path=<?= urlencode($newPath) ?>" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($file['name']) ?></a>
                                    <?php elseif (preg_match('/\.(zip|rar|tar\.gz)$/i', $file['name'])): ?>
                                        <i class="fa-solid fa-file-zipper" style="color: #8b5cf6;"></i>
                                        <?= htmlspecialchars($file['name']) ?>
                                    <?php else: ?>
                                        <i class="fa-regular fa-file" style="color: var(--text-muted);"></i>
                                        <?= htmlspecialchars($file['name']) ?>
                                    <?php endif; ?>
                                </div>
                                <i class="fa-solid fa-pen hover-edit-icon" onclick="openAttrModal('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>'); return false;" style="opacity: 0; cursor: pointer; color: var(--text-muted); font-size: 12px; transition: opacity 0.2s;" title="Переименовать"></i>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($file['size']) ?></td>
                        <td><?= htmlspecialchars($file['perms']) ?></td>
                        <td><?= htmlspecialchars($file['owner']) ?></td>
                        <td><?= htmlspecialchars($file['group']) ?></td>
                        <td><?= htmlspecialchars($file['mtime']) ?></td>
                        <td class="actions-cell">
                            <button class="context-trigger-btn" data-dropdown="dropdown-file-<?= $file['id'] ?>">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="context-dropdown" id="dropdown-file-<?= $file['id'] ?>">
                                <?php if ($file['is_dir']): ?>
                                    <a href="?path=<?= urlencode($newPath) ?>" class="dropdown-item">Открыть каталог</a>
                                <?php endif; ?>
                                <a href="#" class="dropdown-item" onclick="openAttrModal('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>'); return false;">Имя и атрибуты</a>
                                <a href="#" class="dropdown-item" onclick="contextAction('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>', 'copy'); return false;">Копировать</a>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item" onclick="contextAction('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>', 'archive'); return false;">Создать архив</a>
                                <?php if (preg_match('/\.(zip|rar|tar\.gz)$/i', $file['name'])): ?>
                                    <a href="#" class="dropdown-item" onclick="contextAction('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>', 'extract'); return false;">Извлечь</a>
                                <?php else: ?>
                                    <a href="#" class="dropdown-item disabled text-muted" style="pointer-events: none; opacity: 0.5;">Извлечь</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="#" class="dropdown-item text-danger" onclick="deleteFileManagerItem('<?= htmlspecialchars($file['name'], ENT_QUOTES) ?>'); return false;">Удалить</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="padding: 16px 32px; border-top: 1px solid var(--border-color); color: var(--text-muted); font-size: 13px; display: flex; gap: 16px; background: rgba(0,0,0,0.01);">
        <span>Всего: <?= htmlspecialchars($total_items ?? 0) ?></span>
        <span>Размер: <?= htmlspecialchars($total_size ?? '0 KB') ?></span>
    </div>
</div>

<!-- Upload Offcanvas -->
<div id="uploadOffcanvas" class="offcanvas-panel">
    <div class="offcanvas-header">
        <button class="icon-btn" onclick="toggleUploadPanel(false)"><i class="fa-solid fa-xmark"></i></button>
        <span style="font-size: 18px; font-weight: 500;">Загрузить файл</span>
    </div>
    <div class="offcanvas-body">
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; cursor: pointer;">
                <input type="radio" name="upload_type" value="local" checked onchange="toggleUploadType(this)">
                Файл с локального компьютера
            </label>
            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                <input type="radio" name="upload_type" value="url" onchange="toggleUploadType(this)">
                URL файла на другом сервере
            </label>
        </div>

        <!-- Local upload area -->
        <div id="uploadLocalArea" style="background: rgba(99, 102, 241, 0.05); border-radius: 12px; padding: 40px 20px; text-align: center; border: 1px dashed var(--border-color); margin-bottom: 20px;">
            <i class="fa-solid fa-folder-open" style="font-size: 40px; color: var(--text-muted); margin-bottom: 16px;"></i>
            <p style="font-size: 13px; margin-bottom: 16px; color: var(--text-dark);">Перетащите файлы сюда или выберите на вашем локальном компьютере</p>
            <input type="file" id="fileInput" style="display: none;">
            <button class="btn btn-secondary" onclick="document.getElementById('fileInput').click()" style="background: #ffffff; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-radius: 20px;">
                <i class="fa-solid fa-paperclip"></i> Выбрать файл
            </button>
            <div id="selectedFileName" style="margin-top: 10px; font-size: 12px; color: var(--primary-color);"></div>
        </div>

        <!-- URL upload area -->
        <div id="uploadUrlArea" style="display: none; margin-bottom: 20px;">
            <input type="text" id="fileUrlInput" class="form-input" placeholder="https://example.com/file.zip" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);">
        </div>

        <div style="display: flex; gap: 12px;">
            <button class="btn btn-primary" style="background: rgba(99, 102, 241, 0.1); color: var(--text-dark);" onclick="submitUpload()">Загрузить</button>
            <button class="btn btn-secondary" style="background: transparent; border: none;" onclick="toggleUploadPanel(false)">Закрыть</button>
        </div>
    </div>
</div>

<div class="offcanvas-backdrop" id="offcanvasBackdrop" onclick="toggleUploadPanel(false)"></div>

<div class="modal-overlay" id="editModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1100;">
    <div class="modal-container" style="background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 800px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color);">
            <h3 id="editModalTitle" style="margin: 0; font-size: 18px; font-weight: 500;">Редактирование файла</h3>
            <button class="close-modal" onclick="closeEditModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px; display: flex; flex-direction: column;">
            <input type="hidden" id="editFileName">
            <textarea id="editFileContent" style="width: 100%; height: 400px; padding: 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-family: monospace; resize: vertical;"></textarea>
            
            <div class="form-actions" style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn btn-primary" onclick="submitEdit()" style="padding: 8px 16px; border-radius: 6px; border: none; background: rgba(99, 102, 241, 0.1); color: var(--primary-color);">Сохранить</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()" style="padding: 8px 16px; border-radius: 6px; border: none; background: transparent; color: var(--text-dark);">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Attributes Modal -->
<div class="modal-overlay" id="attrModal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 1100;">
    <div class="modal-container" style="background: var(--bg-card); border-radius: 12px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border-color);">
            <h3 id="attrModalTitle" style="margin: 0; font-size: 18px; font-weight: 500;">Атрибуты</h3>
            <button class="close-modal" onclick="closeAttrModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" style="padding: 20px; max-height: 70vh; overflow-y: auto;">
            <form id="attrForm">
                <input type="hidden" name="old_name" id="attrOldName">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Имя</label>
                    <input type="text" name="name" id="attrName" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Владелец</label>
                    <select name="owner" id="attrOwner" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);"></select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="form-label" style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Группа</label>
                    <select name="group" id="attrGroup" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);"></select>
                </div>

                <div class="form-group" id="attrRecursiveGroup" style="display: none; margin-bottom: 15px;">
                    <label class="form-label" style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Изменить дочерние элементы</label>
                    <select name="recursive" id="attrRecursive" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);">
                        <option value="none">не изменять</option>
                        <option value="all">все элементы</option>
                        <option value="dir_only">только директории</option>
                        <option value="file_only">только файлы</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="display: block; font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">Права доступа (число)</label>
                    <input type="text" name="perms" id="attrPerms" class="form-control" maxlength="4" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark);">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px; font-size: 13px;">
                    <div>
                        <strong style="display: block; margin-bottom: 10px;">Права владельца</strong>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="256"> читать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="128"> писать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="64"> исполнять</label>
                    </div>
                    <div>
                        <strong style="display: block; margin-bottom: 10px;">Права группы</strong>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="32"> читать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="16"> писать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="8"> исполнять</label>
                    </div>
                    <div>
                        <strong style="display: block; margin-bottom: 10px;">Права остальных</strong>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="4"> читать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="2"> писать</label>
                        <label style="display: flex; gap: 8px; margin-bottom: 6px; cursor: pointer;"><input type="checkbox" class="perm-cb" data-bit="1"> исполнять</label>
                    </div>
                </div>

                <div class="form-actions" style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-primary" onclick="submitAttr()" style="padding: 8px 16px; border-radius: 6px; border: none; background: rgba(99, 102, 241, 0.1); color: var(--primary-color);">Сохранить</button>
                    <button type="button" class="btn btn-secondary" onclick="closeAttrModal()" style="padding: 8px 16px; border-radius: 6px; border: none; background: transparent; color: var(--text-dark);">Закрыть</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="createArchiveModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1060; justify-content: center; align-items: center;">
    <div style="background: var(--bg-card); width: 450px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 12px;">
                <button style="border:1px solid var(--border-color); background:transparent; border-radius:50%; width:32px; height:32px; cursor:pointer; color: var(--text-muted);" onclick="closeCreateArchiveModal()"><i class="fa-solid fa-xmark"></i></button> 
                Архивировать <span id="createArchiveTitleSuffix" style="color:var(--text-muted); font-weight:normal; font-size:16px;">- log.txt</span>
            </h3>
        </div>
        <div style="padding: 24px;">
            <div style="margin-bottom: 24px; position: relative;">
                <label style="position: absolute; top: -8px; left: 12px; background: var(--bg-card); padding: 0 4px; font-size: 12px; color: var(--text-muted);">Тип</label>
                <select id="archiveType" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-size: 14px; appearance: none;">
                    <option value="zip">архив ZIP (.zip)</option>
                    <option value="tar.gz">архив TAR.GZ (.tar.gz)</option>
                </select>
                <i class="fa-solid fa-chevron-down" style="position: absolute; right: 14px; top: 14px; color: var(--text-muted); pointer-events: none;"></i>
            </div>
            
            <div style="margin-bottom: 24px; position: relative;">
                <label style="position: absolute; top: -8px; left: 12px; background: var(--bg-card); padding: 0 4px; font-size: 12px; color: var(--text-muted);">Имя архива</label>
                <input type="text" id="archiveName" value="archive" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 24px;">
                <label style="display:flex; align-items:center; gap:8px; font-weight: 500; color: var(--text-dark); cursor: pointer;">
                    <input type="checkbox" id="archiveDeleteFiles"> Удалить файлы <i class="fa-solid fa-circle-question" style="color:var(--text-muted);font-size:12px; cursor:help;"></i>
                </label>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 10px;">
                <button onclick="submitCreateArchive()" class="btn btn-primary" style="padding: 10px 24px; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); border: none; font-weight: 500; border-radius: 8px;">Создать</button>
                <button onclick="closeCreateArchiveModal()" class="btn btn-secondary" style="padding: 10px 24px; border: none; background: transparent; font-weight: 500; color: var(--text-dark);">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<div id="globalSearchModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1060; justify-content: center; align-items: center;">
    <div style="background: var(--bg-card); width: 600px; max-height: 90vh; overflow-y: auto; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); display: flex; flex-direction: column;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text-dark); display: flex; align-items: center; gap: 12px;">
                <button style="border:1px solid var(--border-color); background:transparent; border-radius:50%; width:32px; height:32px; cursor:pointer; color: var(--text-muted);" onclick="closeGlobalSearchModal()"><i class="fa-solid fa-xmark"></i></button> 
                Поиск файлов
            </h3>
        </div>
        <div style="padding: 24px;">
            <div style="margin-bottom: 24px; display: flex; gap: 20px; align-items: center;">
                <span style="font-weight: 500; color: var(--text-dark);">Режим <i class="fa-solid fa-circle-question" style="color:var(--text-muted);font-size:12px; cursor:help;"></i></span>
                <label style="display:flex; align-items:center; gap:8px; color: var(--primary-color); cursor: pointer;"><input type="radio" checked> новый поиск</label>
            </div>
            
            <div style="margin-bottom: 20px; position: relative;">
                <label style="position: absolute; top: -8px; left: 12px; background: var(--bg-card); padding: 0 4px; font-size: 12px; color: var(--text-muted);">Каталог <i class="fa-solid fa-circle-question" style="font-size:11px; cursor:help;"></i></label>
                <input type="text" id="gsCatalog" value="/" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-size: 14px;">
            </div>
            <div style="margin-bottom: 24px; position: relative;">
                <label style="position: absolute; top: -8px; left: 12px; background: var(--bg-card); padding: 0 4px; font-size: 12px; color: var(--text-muted);">Маска имени <i class="fa-solid fa-circle-question" style="font-size:11px; cursor:help;"></i></label>
                <input type="text" id="gsMask" value="*" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display:flex; align-items:center; gap:8px; font-weight: 500; color: var(--primary-color); cursor: pointer;">
                    <input type="checkbox" id="gsRecursive" checked> Искать в подкаталогах <i class="fa-solid fa-circle-question" style="color:var(--text-muted);font-size:12px; cursor:help;"></i>
                </label>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:flex; align-items:center; gap:8px; font-weight: 500; color: var(--text-dark); cursor: pointer;">
                    <input type="checkbox" id="gsByContent" onchange="document.getElementById('gsContentContainer').style.display = this.checked ? 'block' : 'none'"> Искать по содержимому <i class="fa-solid fa-circle-question" style="color:var(--text-muted);font-size:12px; cursor:help;"></i>
                </label>
            </div>
            <div id="gsContentContainer" style="display: none; margin-bottom: 24px; margin-left: 24px;">
                <div style="position: relative;">
                    <label style="position: absolute; top: -8px; left: 12px; background: var(--bg-card); padding: 0 4px; font-size: 12px; color: var(--text-muted);">Текст для поиска</label>
                    <input type="text" id="gsContentText" style="width: 100%; padding: 12px 14px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-dark); font-size: 14px;">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 28px;">
                <button onclick="submitGlobalSearch()" class="btn btn-primary" style="padding: 10px 24px; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); border: none; font-weight: 500; border-radius: 8px;">Найти</button>
                <button onclick="closeGlobalSearchModal()" class="btn btn-secondary" style="padding: 10px 24px; border: none; background: transparent; font-weight: 500; color: var(--text-dark);">Закрыть</button>
            </div>
            
            <!-- Результаты поиска -->
            <div id="gsResultsContainer" style="display: none; margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 20px;">
                <h4 style="margin: 0 0 12px 0; font-size: 14px; color: var(--text-dark);">Результаты поиска <span id="gsResultsCount" style="color:var(--text-muted); font-weight:normal;"></span></h4>
                <div id="gsResultsList" style="max-height: 250px; overflow-y: auto; font-size: 13px; border: 1px solid var(--border-color); border-radius: 6px;"></div>
            </div>
        </div>
    </div>
</div>

<div id="copyModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1060; justify-content: center; align-items: center;">
    <div style="background: var(--bg-card); width: 600px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 id="copyModalTitle" style="margin: 0; font-size: 18px; font-weight: 600; color: var(--text-dark);">Копировать</h3>
            <button onclick="closeCopyModal()" style="background: none; border: none; cursor: pointer; color: var(--text-muted); font-size: 18px;"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding: 24px;">
            <div style="margin-bottom: 8px; color: var(--text-muted); font-size: 13px;">В каталог</div>
            <div id="copyDirTree" style="height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 6px; padding: 12px; background: var(--input-bg); user-select: none;">
                <!-- Tree items -->
                <div style="color: var(--text-muted); font-size: 14px;">Загрузка каталогов...</div>
            </div>
            
            <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 12px;">
                <label style="display: flex; gap: 8px; align-items: center; cursor: pointer; color: var(--text-dark);">
                    <input type="checkbox" id="copyMoveMode" onchange="updateCopyBtnText()"> Перенести файлы <i class="fa-regular fa-circle-question" style="color: var(--text-muted); font-size: 12px;" title="Оригиналы будут удалены"></i>
                </label>
                <label style="display: flex; gap: 8px; align-items: center; cursor: pointer; color: var(--text-dark);">
                    <input type="checkbox" id="copyOverwrite"> Перезаписать <i class="fa-regular fa-circle-question" style="color: var(--text-muted); font-size: 12px;" title="Если файлы уже существуют"></i>
                </label>
                <label style="display: flex; gap: 8px; align-items: center; cursor: pointer; color: var(--text-dark);">
                    <input type="checkbox" id="copyGoToDir"> Перейти в выбранный каталог <i class="fa-regular fa-circle-question" style="color: var(--text-muted); font-size: 12px;" title="Откроет папку назначения после завершения"></i>
                </label>
            </div>
            
            <div style="margin-top: 24px;">
                <button type="button" class="btn btn-primary" onclick="submitCopy()" style="padding: 8px 16px; border-radius: 6px; border: none; background: rgba(99, 102, 241, 0.1); color: var(--primary-color); font-weight: 500;" id="copySubmitBtn">Копировать</button>
            </div>
        </div>
    </div>
</div>

<style>
.dropdown-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 4px 0;
}
.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}
.btn-sm i {
    font-size: 14px;
}
.toolbar-container {
    padding: 24px 32px 16px 32px; 
}
.btn-secondary {
    background: transparent;
    border: none;
}
.btn-secondary:hover:not(:disabled) {
    background: rgba(0, 0, 0, 0.05);
}
[data-theme="dark"] .btn-secondary:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.1);
}
.toolbar-left .btn-secondary.disabled {
    opacity: 0.4;
}

/* Offcanvas Styles */
.offcanvas-panel {
    position: fixed;
    top: 0;
    right: -450px;
    width: 450px;
    height: 100vh;
    background: var(--bg-card);
    box-shadow: -5px 0 25px rgba(0,0,0,0.1);
    z-index: 1050;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}
.offcanvas-panel.show {
    right: 0;
}
.offcanvas-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px;
    border-bottom: 1px solid var(--border-color);
}
.offcanvas-body {
    padding: 24px;
    flex: 1;
    overflow-y: auto;
}
.offcanvas-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.4);
    z-index: 1040;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}
.hover-edit-icon:hover {
    color: var(--primary-color) !important;
}
tr:hover .hover-edit-icon {
    opacity: 1 !important;
}
</style>

<script>
function toggleAllFiles(checkbox) {
    const checkboxes = document.querySelectorAll('.file-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateToolbarState();
}

function updateToolbarState() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    const checkedCount = checked.length;
    
    const delBtn = document.querySelector('.toolbar-del-btn');
    const attrBtn = document.querySelector('.toolbar-attr-btn');
    const editBtn = document.querySelector('.toolbar-edit-btn');
    const downBtn = document.querySelector('button[onclick="downloadSelected()"]');
    const copyBtn = document.querySelector('.toolbar-copy-btn');
    if (delBtn) {
        if (checkedCount > 0) {
            delBtn.classList.remove('disabled');
            delBtn.removeAttribute('disabled');
        } else {
            delBtn.classList.add('disabled');
            delBtn.setAttribute('disabled', 'true');
        }
    }
    
    if (editBtn) {
        if (checkedCount === 1 && !checked[0].closest('tr').querySelector('.fa-folder')) {
            editBtn.classList.remove('disabled');
            editBtn.removeAttribute('disabled');
        } else {
            editBtn.classList.add('disabled');
            editBtn.setAttribute('disabled', 'disabled');
        }
    }

    if (attrBtn) {
        if (checkedCount === 1) {
            attrBtn.classList.remove('disabled');
            attrBtn.removeAttribute('disabled');
        } else {
            attrBtn.classList.add('disabled');
            attrBtn.setAttribute('disabled', 'true');
        }
    }


    if (downBtn) {
        if (checkedCount === 1 && !checked[0].closest('tr').querySelector('.fa-folder')) {
            downBtn.classList.remove('disabled');
            downBtn.removeAttribute('disabled');
        } else {
            downBtn.classList.add('disabled');
            downBtn.setAttribute('disabled', 'true');
        }
    }
    
    if (copyBtn) {
        if (checkedCount > 0) {
            copyBtn.classList.remove('disabled');
            copyBtn.removeAttribute('disabled');
        } else {
            copyBtn.classList.add('disabled');
            copyBtn.setAttribute('disabled', 'true');
        }
    }
    
    const archiveBtn = document.getElementById('toolbarArchiveBtn');
    const extractArchiveBtn = document.getElementById('extractArchiveBtn');
    if (archiveBtn) {
        if (checkedCount > 0) {
            archiveBtn.classList.remove('disabled');
            archiveBtn.removeAttribute('disabled');
        } else {
            archiveBtn.classList.add('disabled');
            archiveBtn.setAttribute('disabled', 'true');
        }
        
        if (extractArchiveBtn) {
            let canExtract = false;
            if (checkedCount === 1) {
                const fileName = checked[0].value.toLowerCase();
                if (fileName.endsWith('.zip') || fileName.endsWith('.rar') || fileName.endsWith('.tar.gz')) {
                    canExtract = true;
                }
            }
            if (canExtract) {
                extractArchiveBtn.style.opacity = '1';
                extractArchiveBtn.style.pointerEvents = 'auto';
            } else {
                extractArchiveBtn.style.opacity = '0.4';
                extractArchiveBtn.style.pointerEvents = 'none';
            }
        }
    }
    
    // Update selected count and size in footer
    const footerStats = document.querySelector('div[style*="border-top: 1px solid var(--border-color)"]');
    if (footerStats && checkedCount > 0) {
        let totalSize = 0;
        checked.forEach(cb => {
            totalSize += parseInt(cb.getAttribute('data-size')) || 0;
        });
        
        const formatSize = (bytes) => {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        };
        
        if (!document.getElementById('selectedStats')) {
            const span = document.createElement('span');
            span.id = 'selectedStats';
            span.style.color = 'var(--primary-color)';
            span.style.fontWeight = '500';
            footerStats.prepend(span);
        }
        document.getElementById('selectedStats').innerText = `Выделено: ${checkedCount}  Размер: ${formatSize(totalSize)}`;
    } else if (document.getElementById('selectedStats')) {
        document.getElementById('selectedStats').remove();
    }
}

function downloadSelected() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length !== 1) return;
    
    const filename = checked[0].value;
    const url = new URL(window.location.href);
    const path = url.searchParams.get('path') || '/';
    
    window.location.href = '/api/manager/download?path=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(filename);
}

function editSelected() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length !== 1) return;
    
    const filename = checked[0].value;
    const url = new URL(window.location.href);
    const path = url.searchParams.get('path') || '/';
    
    document.getElementById('editFileName').value = filename;
    document.getElementById('editModalTitle').innerText = `Редактирование: ${filename}`;
    document.getElementById('editFileContent').value = 'Загрузка...';
    document.getElementById('editModal').style.display = 'flex';
    
    fetch('/api/manager/read?path=' + encodeURIComponent(path) + '&name=' + encodeURIComponent(filename), {
        headers: { 'X-Panel-Token': localStorage.getItem('panel_token') || '' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('editFileContent').value = data.content;
        } else {
            document.getElementById('editFileContent').value = 'Ошибка: ' + (data.message || 'Не удалось прочитать файл');
        }
    })
    .catch(e => {
        document.getElementById('editFileContent').value = 'Ошибка: ' + e.message;
    });
}

function togglePathEdit(show) {
    if (show) {
        document.getElementById('breadcrumbDisplay').style.display = 'none';
        document.getElementById('breadcrumbEdit').style.display = 'flex';
        document.getElementById('pathToolsNormal').style.display = 'none';
        document.getElementById('pathToolsEdit').style.display = 'flex';
        document.getElementById('pathEditInput').focus();
    } else {
        document.getElementById('breadcrumbDisplay').style.display = 'flex';
        document.getElementById('breadcrumbEdit').style.display = 'none';
        document.getElementById('pathToolsNormal').style.display = 'flex';
        document.getElementById('pathToolsEdit').style.display = 'none';
    }
}

function submitPathEdit() {
    const newPath = document.getElementById('pathEditInput').value;
    window.location.href = '?path=' + encodeURIComponent(newPath);
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pathEditInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') submitPathEdit();
        if (e.key === 'Escape') togglePathEdit(false);
    });
});

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function submitEdit() {
    const filename = document.getElementById('editFileName').value;
    const content = document.getElementById('editFileContent').value;
    const url = new URL(window.location.href);
    const path = url.searchParams.get('path') || '/';
    
    fetch('/api/manager/write', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-Panel-Token': localStorage.getItem('panel_token') || ''
        },
        body: JSON.stringify({ path, name: filename, content })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            alert('Файл успешно сохранен');
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось сохранить'));
        }
    })
    .catch(e => alert('Ошибка запроса: ' + e.message));
}

var selectedCopyPath = '/';
var copyItems = [];

function contextAction(filename, action) {
    document.querySelectorAll('.context-dropdown').forEach(d => d.classList.remove('show'));
    document.querySelectorAll('.file-checkbox').forEach(cb => cb.checked = false);
    const cb = document.querySelector('.file-checkbox[value="' + filename.replace(/"/g, '\\"') + '"]');
    if (cb) cb.checked = true;
    updateToolbarState();
    
    if (action === 'copy') openCopyModal();
    if (action === 'archive') openCreateArchiveModal();
    if (action === 'extract') extractSelectedArchive();
}

function openCopyModal() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length === 0) return;
    
    copyItems = Array.from(checked).map(cb => cb.value);
    document.getElementById('copyModalTitle').innerText = 'Копировать - ' + copyItems.join(', ');
    document.getElementById('copyMoveMode').checked = false;
    document.getElementById('copyOverwrite').checked = false;
    document.getElementById('copyGoToDir').checked = false;
    updateCopyBtnText();
    
    document.getElementById('copyModal').style.display = 'flex';
    
    const url = new URL(window.location.href);
    const currentPath = url.searchParams.get('path') || '/';
    
    selectedCopyPath = currentPath;
    loadDirTree(currentPath, document.getElementById('copyDirTree'));
}

function closeCopyModal() {
    document.getElementById('copyModal').style.display = 'none';
}

function updateCopyBtnText() {
    const isMove = document.getElementById('copyMoveMode').checked;
    document.getElementById('copySubmitBtn').innerText = isMove ? 'Переместить' : 'Копировать';
}

function loadDirTree(path, container) {
    container.innerHTML = '<div style="color: var(--text-muted); font-size: 14px; padding: 4px;">Загрузка...</div>';
    fetch('/api/manager/tree?path=' + encodeURIComponent(path))
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                container.innerHTML = '';
                if (path !== '/') {
                    const upDiv = document.createElement('div');
                    upDiv.style.cursor = 'pointer';
                    upDiv.style.padding = '4px 8px';
                    upDiv.style.color = 'var(--text-dark)';
                    upDiv.innerHTML = '<i class="fa-solid fa-arrow-turn-up" style="margin-right: 8px;"></i> .. (Вверх)';
                    const parentPath = path.substring(0, path.lastIndexOf('/')) || '/';
                    upDiv.onclick = () => {
                        selectedCopyPath = parentPath;
                        loadDirTree(parentPath, document.getElementById('copyDirTree'));
                    };
                    container.appendChild(upDiv);
                }
                
                const curDiv = document.createElement('div');
                curDiv.style.padding = '6px 8px';
                curDiv.style.background = 'rgba(99, 102, 241, 0.1)';
                curDiv.style.borderRadius = '4px';
                curDiv.style.fontWeight = '500';
                curDiv.style.color = 'var(--primary-color)';
                curDiv.style.marginBottom = '8px';
                curDiv.innerHTML = '<i class="fa-solid fa-folder-open" style="margin-right: 8px;"></i> ' + path;
                container.appendChild(curDiv);
                
                data.dirs.forEach(dir => {
                    const div = document.createElement('div');
                    div.style.cursor = 'pointer';
                    div.style.padding = '4px 8px';
                    div.style.color = 'var(--text-dark)';
                    div.style.display = 'flex';
                    div.style.alignItems = 'center';
                    div.innerHTML = '<i class="fa-solid fa-folder" style="color: #f59e0b; margin-right: 8px;"></i> ' + dir.name;
                    div.onmouseover = () => div.style.background = 'rgba(0,0,0,0.05)';
                    div.onmouseout = () => div.style.background = 'none';
                    div.onclick = () => {
                        selectedCopyPath = dir.path;
                        loadDirTree(dir.path, document.getElementById('copyDirTree'));
                    };
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<div style="color: red;">Ошибка: ' + (data.message || '') + '</div>';
            }
        })
        .catch(e => container.innerHTML = '<div style="color: red;">Ошибка: ' + e.message + '</div>');
}

function submitCopy() {
    const isMove = document.getElementById('copyMoveMode').checked;
    const overwrite = document.getElementById('copyOverwrite').checked;
    const goToDir = document.getElementById('copyGoToDir').checked;
    
    const url = new URL(window.location.href);
    const currentPath = url.searchParams.get('path') || '/';
    
    const absoluteItems = copyItems.map(item => {
        let cleanCurrent = currentPath.replace(/\/+$/, '');
        if (cleanCurrent === '') cleanCurrent = '/';
        return cleanCurrent === '/' ? '/' + item : cleanCurrent + '/' + item;
    });
    
    const endpoint = isMove ? '/api/manager/move' : '/api/manager/copy';
    
    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Panel-Token': localStorage.getItem('panel_token') || ''
        },
        body: JSON.stringify({
            items: absoluteItems,
            target: selectedCopyPath,
            overwrite: overwrite
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeCopyModal();
            if (goToDir) {
                window.location.href = '?path=' + encodeURIComponent(selectedCopyPath);
            } else {
                window.location.reload();
            }
        } else {
            alert('Ошибка: ' + (data.message || 'Не удалось выполнить действие'));
        }
    })
    .catch(e => alert('Ошибка запроса: ' + e.message));
}
function toggleQuickSearch(show = true) {
    const btn = document.getElementById('quickSearchBtn');
    const container = document.getElementById('quickSearchContainer');
    const input = document.getElementById('quickSearchInput');
    
    if (show && container.style.display === 'none') {
        container.style.display = 'flex';
        input.value = '';
        input.focus();
        filterFiles();
    } else if (!show) {
        container.style.display = 'none';
        input.value = '';
        filterFiles();
    }
}

function filterFiles() {
    const term = document.getElementById('quickSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.data-table tbody tr');
    
    rows.forEach(row => {
        const nameCell = row.querySelector('td:nth-child(2) a');
        if (nameCell) {
            const name = nameCell.innerText.toLowerCase();
            row.style.display = name.includes(term) ? '' : 'none';
        }
    });
}
function openGlobalSearchModal() {
    const url = new URL(window.location.href);
    const currentPath = url.searchParams.get('path') || '/';
    document.getElementById('gsCatalog').value = currentPath;
    document.getElementById('gsResultsContainer').style.display = 'none';
    document.getElementById('gsResultsList').innerHTML = '';
    document.getElementById('globalSearchModal').style.display = 'flex';
}

function closeGlobalSearchModal() {
    document.getElementById('globalSearchModal').style.display = 'none';
}

function submitGlobalSearch() {
    const path = document.getElementById('gsCatalog').value;
    const mask = document.getElementById('gsMask').value || '*';
    const recursive = document.getElementById('gsRecursive').checked;
    const byContent = document.getElementById('gsByContent').checked;
    const contentText = document.getElementById('gsContentText').value;
    
    if (byContent && !contentText) {
        alert('Введите текст для поиска по содержимому');
        return;
    }
    
    const resultsList = document.getElementById('gsResultsList');
    const resultsContainer = document.getElementById('gsResultsContainer');
    const resultsCount = document.getElementById('gsResultsCount');
    
    resultsContainer.style.display = 'block';
    resultsList.innerHTML = '<div style="padding:12px; color:var(--text-muted);">Выполняется поиск...</div>';
    resultsCount.innerText = '';
    
    fetch('/api/manager/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Panel-Token': localStorage.getItem('panel_token') || ''
        },
        body: JSON.stringify({ path, mask, recursive, contentText: byContent ? contentText : null })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            resultsList.innerHTML = '';
            resultsCount.innerText = '(' + data.files.length + ')';
            if (data.files.length === 0) {
                resultsList.innerHTML = '<div style="padding:12px; color:var(--text-muted);">Ничего не найдено</div>';
            } else {
                data.files.forEach(file => {
                    const item = document.createElement('div');
                    item.style.padding = '8px 12px';
                    item.style.borderBottom = '1px solid var(--border-color)';
                    item.style.cursor = 'pointer';
                    item.style.display = 'flex';
                    item.style.alignItems = 'center';
                    item.style.gap = '8px';
                    item.onmouseover = () => item.style.background = 'rgba(0,0,0,0.02)';
                    item.onmouseout = () => item.style.background = 'transparent';
                    
                    let icon = '';
                    if (file.is_dir) {
                        icon = '<i class="fa-solid fa-folder" style="color: #f59e0b;"></i>';
                    } else if (file.name.toLowerCase().match(/\.(zip|rar|tar\.gz)$/)) {
                        icon = '<i class="fa-solid fa-file-zipper" style="color: #8b5cf6;"></i>';
                    } else {
                        icon = '<i class="fa-regular fa-file" style="color: var(--primary-color);"></i>';
                    }
                    
                    item.innerHTML = `
                        <div style="flex-shrink: 0;">${icon}</div>
                        <div style="flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${file.path}">
                            <div style="font-weight: 500; color: var(--text-dark);">${file.name}</div>
                            <div style="font-size: 11px; color: var(--text-muted);">${file.path}</div>
                        </div>
                    `;
                    
                    item.onclick = () => {
                        let targetPath = file.is_dir ? file.path : file.path.substring(0, file.path.lastIndexOf('/'));
                        if (!targetPath) targetPath = '/';
                        window.location.href = '?path=' + encodeURIComponent(targetPath);
                    };
                    
                    resultsList.appendChild(item);
                });
            }
        } else {
            resultsList.innerHTML = `<div style="padding:12px; color:var(--danger);">Ошибка: ${data.message}</div>`;
        }
    })
    .catch(e => {
        resultsList.innerHTML = `<div style="padding:12px; color:var(--danger);">Ошибка запроса: ${e.message}</div>`;
    });
}
function openCreateArchiveModal() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length === 0) return;
    
    let titleSuffix = '';
    let defaultName = 'archive';
    
    if (checked.length === 1) {
        titleSuffix = '- ' + checked[0].value;
        const nameParts = checked[0].value.split('.');
        if (nameParts.length > 1 && !checked[0].closest('tr').querySelector('.fa-folder')) {
            nameParts.pop();
            defaultName = nameParts.join('.');
        } else {
            defaultName = checked[0].value;
        }
    } else {
        titleSuffix = '- ' + checked.length + ' файлов';
        defaultName = 'archive_' + checked.length;
    }
    
    document.getElementById('createArchiveTitleSuffix').innerText = titleSuffix;
    document.getElementById('archiveName').value = defaultName;
    document.getElementById('archiveDeleteFiles').checked = false;
    document.getElementById('archiveType').value = 'zip';
    
    document.getElementById('createArchiveModal').style.display = 'flex';
}

function closeCreateArchiveModal() {
    document.getElementById('createArchiveModal').style.display = 'none';
}

function submitCreateArchive() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length === 0) return;
    
    const items = Array.from(checked).map(cb => cb.value);
    const archiveNameInput = document.getElementById('archiveName').value.trim();
    const archiveType = document.getElementById('archiveType').value;
    const deleteFiles = document.getElementById('archiveDeleteFiles').checked;
    
    if (!archiveNameInput) {
        alert('Укажите имя архива');
        return;
    }
    
    let finalArchiveName = archiveNameInput;
    if (!finalArchiveName.endsWith('.' + archiveType)) {
        if (archiveType === 'tar.gz' && finalArchiveName.endsWith('.tar')) {
            finalArchiveName += '.gz';
        } else {
            finalArchiveName += '.' + archiveType;
        }
    }
    
    const url = new URL(window.location.href);
    const currentPath = url.searchParams.get('path') || '/';
    
    fetch('/api/manager/compress', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Panel-Token': localStorage.getItem('panel_token') || ''
        },
        body: JSON.stringify({ 
            path: currentPath, 
            files: items, 
            archive_name: finalArchiveName,
            delete_files: deleteFiles,
            archive_type: archiveType
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            closeCreateArchiveModal();
            window.location.reload();
        } else {
            alert('Ошибка создания архива: ' + data.message);
        }
    })
    .catch(e => alert('Ошибка запроса: ' + e.message));
}

function extractSelectedArchive() {
    const checked = document.querySelectorAll('.file-checkbox:checked');
    if (checked.length !== 1) return;
    
    const fileName = checked[0].value;
    if (!fileName.toLowerCase().match(/\.(zip|rar|tar\.gz)$/)) {
        alert('Выбранный файл не является поддерживаемым архивом.');
        return;
    }
    
    if (!confirm('Распаковать архив ' + fileName + ' в текущую директорию?')) {
        return;
    }
    
    const url = new URL(window.location.href);
    const currentPath = url.searchParams.get('path') || '/';
    
    fetch('/api/manager/extract', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Panel-Token': localStorage.getItem('panel_token') || ''
        },
        body: JSON.stringify({ 
            path: currentPath, 
            file: fileName 
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Ошибка распаковки: ' + data.message);
        }
    })
    .catch(e => alert('Ошибка запроса: ' + e.message));
}
</script>


