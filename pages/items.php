<?php

/**
 * This file is part of the Feeds package.
 *
 * @author FriendsOfREDAXO
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$func = rex_request('func', 'string');
$id = rex_request('id', 'integer');

if ($func == 'setstatus') {
    $status = (rex_request('oldstatus', 'int') + 1) % 2;
    rex_sql::factory()
        ->setTable(rex_feeds_item::table())
        ->setWhere('id = :id', ['id' => $id])
        ->setValue('status', $status)
        ->addGlobalUpdateFields()
        ->update();
    echo rex_view::success($this->i18n('item_status_saved'));
    $func = '';
}

if ('' == $func) {
    $query = 'SELECT
                i.id,
                s.namespace,
                i.media,
                s.type,
                (CASE WHEN (i.title IS NULL or i.title = "")
                    THEN i.content
                    ELSE i.title
                END) as title,
                i.url,
                i.status
            FROM
                ' . rex_feeds_item::table() . ' AS i
                LEFT JOIN
                    ' . rex_feeds_stream::table() . ' AS s
                    ON  i.stream_id = s.id
            ORDER BY i.date DESC, id DESC
            ';

    $list = rex_list::factory($query);
    $list->addTableAttribute('class', 'table-striped');

    $list->addColumn('', '', 0, ['<th class="rex-table-icon">###VALUE###</th>', '<td class="rex-table-icon">###VALUE###</td>']);
    $list->setColumnParams('', ['func' => 'edit', 'id' => '###id###']);
    $list->setColumnFormat('', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        $type = explode('_', $list->getValue('s.type'));
        $icon = 'fa-paper-plane-o';
        if (isset($type[0])) {
            switch ($type[0]) {
                case 'rss':
                    $icon = 'fa-rss';
                    break;
                case 'twitter':
                    $icon = 'fa-twitter';
                    break;
                case 'youtube':
                    $icon = 'fa-youtube';
                    break;
                case 'google':
                    $icon = 'fa-google';
                    break;
            }
            return $list->getColumnLink('', '<i class="rex-icon ' . $icon . (($list->getValue('status')) ? '' : ' text-muted') . '"></i>');
        }
    });

    $list->removeColumn('id');
    $list->removeColumn('url');
    $list->removeColumn('type');

    $list->setColumnLabel('namespace', $this->i18n('stream_namespace') . '/' . $this->i18n('stream_type'));
    $list->setColumnFormat('namespace', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        $namespace = $list->getValue('namespace');
        $type = $list->getValue('type');
        $out = $namespace . '<br /><small>' . $type . '</small>';
        $out = '<span class="type' . (($list->getValue('status')) ? '' : ' text-muted') . '">' . $out . '</span>';
        return $out;
    });

    $list->setColumnLabel('title', $this->i18n('item_title'));
    $list->setColumnFormat('title', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        $title = $list->getValue('title');
        $title = rex_formatter::truncate($title, ['length' => 140]);
        $title .= ($list->getValue('url') != '') ? '<br /><small><a href="' . $list->getValue('url') . '" target="_blank">' . $list->getValue('url') . '</a></small>' : '';
        $title = '<div style="word-wrap:break-word; max-width:310px; max-width:40vw;"><span class="title' . (($list->getValue('status')) ? '' : ' text-muted') . '">' . $title . '</span></div>';
        return $title;
    });
    
    $list->setColumnLabel('media', $this->i18n('item_media'));
    $list->setColumnFormat('media', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        $media = $list->getValue('media');
        $media = ($media != '') ? '<div class="img-thumbnail"><div style="width:60px; height:60px; overflow:hidden; background: #333 url(\'' . $media . '\') no-repeat; background-size:contain;' . ((!$list->getValue('status')) ? '; opacity:.4' : '') . '">&nbsp;</div></div>' : '';
        return $media;
    });

    $list->setColumnLabel('status', $this->i18n('status'));
    $list->setColumnParams('status', ['func' => 'setstatus', 'oldstatus' => '###status###', 'id' => '###id###']);
    $list->setColumnLayout('status', ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnFormat('status', 'custom', function ($params) {
        /** @var rex_list $list */
        $list = $params['list'];
        if ($list->getValue('status') == 1) {
            $str = $list->getColumnLink('status', '<span class="rex-online"><i class="rex-icon rex-icon-active-true"></i> ' . $this->i18n('item_status_online') . '</span>');
        } else {
            $str = $list->getColumnLink('status', '<span class="rex-offline"><i class="rex-icon rex-icon-active-false"></i> ' . $this->i18n('item_status_offline') . '</span>');
        }
        return $str;
    });

    $list->addColumn($this->i18n('function'), $this->i18n('edit'));
    $list->setColumnLayout($this->i18n('function'), ['<th class="rex-table-action">###VALUE###</th>', '<td class="rex-table-action">###VALUE###</td>']);
    $list->setColumnParams($this->i18n('function'), ['func' => 'edit', 'id' => '###id###']);

    $content = $list->get();

    $fragment = new rex_fragment();
    $fragment->setVar('title', $this->i18n('items'));
    $fragment->setVar('content', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
} else {
    $title = $func == 'edit' ? $this->i18n('item_edit') : $this->i18n('item_add');

    $form = rex_form::factory(rex_feeds_item::table(), '', 'id = ' . $id, 'post', false);
    $form->addParam('id', $id);
    $form->setApplyUrl(rex_url::currentBackendPage());
    $form->setEditMode($func == 'edit');
    $add = $func != 'edit';

    $field = $form->addHiddenField('changed_by_user', 1);

    $field = $form->addTextField('uid');
    $field->setLabel($this->i18n('item_uid'));

    if ($media = $form->getSql()->getValue('type')) {
        $field = $form->addTextField('type');
        $field->setLabel($this->i18n('item_type'));
    }

    $field = $form->addTextField('title');
    $field->setLabel($this->i18n('item_title'));

    $field = $form->addTextAreaField('content');
    $field->setLabel($this->i18n('item_content'));

    $field = $form->addTextAreaField('content_raw');
    $field->setLabel($this->i18n('item_content_raw'));

    $field = $form->addTextField('url');
    $field->setLabel($this->i18n('item_url'));

    $field = $form->addReadOnlyField('date');
    $field->setLabel($this->i18n('item_date'));

    $field = $form->addTextField('author');
    $field->setLabel($this->i18n('item_author'));

    $field = $form->addTextField('language');
    $field->setLabel($this->i18n('item_language'));

    $field = $form->addSelectField('status');
    $field->setLabel($this->i18n('status'));
    $select = $field->getSelect();
    $select->setSize(1);
    $select->addOption($this->i18n('item_status_online'), 1);
    $select->addOption($this->i18n('item_status_offline'), 0);

    if ($media = $form->getSql()->getValue('mediasource')) {
        $field = $form->addTextField('mediasource');
        $field->setLabel($this->i18n('item_mediasource'));
    }
    if ($media = $form->getSql()->getValue('media')) {
        $form->addRawField('<p class="text-center"><img src="'.$media.'" style="max-height: 300px"/></p>');
    }

    $content = $form->get();

    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit');
    $fragment->setVar('title', $title);
    $fragment->setVar('body', $content, false);
    $content = $fragment->parse('core/page/section.php');

    echo $content;
}
