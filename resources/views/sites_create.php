<div class="form-page-container">
    <div class="page-card" style="padding: 32px; margin: 0;">
    <div class="form-page-header">
        <h2>Новый сайт</h2>
    </div>

    <form id="createSiteForm" class="panel-form">
        <div class="form-group-row">
            <label for="domain">Доменное имя*</label>
            <input type="text" id="domain" name="domain" placeholder="site.uz" required autocomplete="off">
        </div>

        <div class="form-group-row">
            <label for="handler">Обработчик</label>
            <select id="handler" name="handler">
                <option value="PHP">PHP</option>
                <option value="NodeJS">NodeJS</option>
                <option value="Static">Static HTML</option>
            </select>
        </div>

        <div class="form-group-row">
            <label for="cms">CMS</label>
            <select id="cms" name="cms">
                <option value="none">Не устанавливать</option>
                <option value="wordpress">WordPress</option>
                <option value="laravel">Laravel Skeleton</option>
            </select>
        </div>

        <div class="form-group-row">
            <label for="ssl">SSL-сертификат</label>
            <select id="ssl" name="ssl">
                <option value="letsencrypt">Новый бесплатный от Let's Encrypt</option>
                <option value="none">Не устанавливать</option>
            </select>
        </div>

        <div class="form-group-row" id="version-group">
            <label for="version">Версия</label>
            <select id="version" name="version">
                <option value="<?= $phpVer ?>"><?= $phpVer ?> (System Default)</option>
            </select>
        </div>

        <div id="formError" class="form-error-msg hidden"></div>
        <div id="formSuccess" class="form-success-msg hidden"></div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Создать</button>
            <a href="/sites" class="btn btn-secondary" data-module>Отмена</a>
        </div>
    </form>
    </div>
</div>