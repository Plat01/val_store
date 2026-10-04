(function (wp) {
  const sections = [
    ['hero', 'Первый экран'], ['category_grid', 'Сетка категорий'],
    ['featured', 'Товары'], ['benefits', 'Преимущества'],
    ['assembly', 'Как собрать станок'], ['faq', 'Частые вопросы'],
    ['contacts', 'Контакты и карта'], ['header', 'Шапка'], ['footer', 'Подвал'],
    ['catalog', 'Каталог с фильтрами'], ['product', 'Карточка товара'],
    ['search', 'Результаты поиска'], ['not_found', 'Страница 404']
  ];
  wp.blocks.registerBlockType('ss/view', {
    title: 'Секция магазина', icon: 'store', category: 'widgets',
    attributes: { view: { type: 'string', default: 'benefits' } },
    edit: function (props) {
      return wp.element.createElement(wp.element.Fragment, null,
        wp.element.createElement(wp.blockEditor.InspectorControls, null,
          wp.element.createElement(wp.components.PanelBody, { title: 'Секция магазина' },
            wp.element.createElement(wp.components.SelectControl, {
              label: 'Содержимое', value: props.attributes.view,
              options: sections.map(([value, label]) => ({ value, label })),
              onChange: view => props.setAttributes({ view })
            })
          )
        ),
        wp.element.createElement(wp.serverSideRender, {
          block: 'ss/view', attributes: props.attributes
        })
      );
    },
    save: function () { return null; }
  });
})(window.wp);
