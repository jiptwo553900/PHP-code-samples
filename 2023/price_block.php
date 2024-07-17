<div class="purchase-block__price">
    <div class="purchase-block__price-item">
        <div class="price">
            <? if ($arParams['SHOW_OLD_PRICE'] === 'Y' && $showDiscount): ?>
                <div class="price__discount-flag">
                    <div class="product-discount product-discount--large product-discount--secondary">
                        <?=$price['PERCENT']?>%
                    </div>
                </div>
            <? endif; ?>
            <div class="price__basic">
                <?=$price['PRINT_RATIO_PRICE']?>/<?=$arItem['ITEM_MEASURE']['TITLE']?>
            </div>
            <? if ($arParams['SHOW_OLD_PRICE'] === 'Y' && $showDiscount): ?>
                <div class="price__discount">
                    <?=$price['PRINT_RATIO_BASE_PRICE']?>
                </div>
            <? endif; ?>
        </div>
    </div>
    <div class="purchase-block__price-item">
    </div>
</div>

<? if (false): // Блок "Торг уместен" временно скрываем?>
    <div class="bidding mb-4 d-lg-none d-flex">
        Торг уместен!
        <button type="button"
                class="btn btn-custom btn-custom--tooltip"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                data-bs-title="Торг уместен!">
            <?=\Local\Tools::getSvg('tooltip', '')?>
            <span class="visually-hidden">Торг уместен!</span>
        </button>
    </div>
<? endif; ?>

<div class="purchase-block__btn" data-url="/" data-card>
    <div class="purchase-block__btn-favorite">
        <button type="button"
                class="product-favorite product-favorite--large product-favorite--outline">
            <?=\Local\Tools::getSvg('like', 'product-favorite__like')?>
            <span class="visually-hidden">Избранное</span>
        </button>
    </div>
    <div class="product-buttons product-buttons--lg js-product-buttons" data-id="<?=$arItem['ID']?>">
        <div class="product-buttons__add">
            <button type="button" class="btn btn-lg btn-custom btn-custom--load w-100 js-component js-buy-btn"
                    <?=$arItem['PRODUCT']['QUANTITY'] > 0 ? '' : 'disabled'?>
                    data-component="ProductButton">
                <span class="btn-custom__spinner">
                    <span class="spinner">
                        <span class="spinner__angle spinner__angle--first"></span>
                        <span class="spinner__angle spinner__angle--two"></span>
                        <span class="spinner__angle spinner__angle--three"></span>
                        <span class="spinner__angle"></span>
                    </span> 
                </span>
                <span class="btn-custom__text">
                    <?=$arItem['PRODUCT']['QUANTITY'] > 0 ? 'В корзину' : 'Нет в наличии'?>
                </span>
            </button>
        </div>
        <div class="product-buttons__count" data-counter>
            <button type="button" class="product-buttons__count-button"
                    data-button-remove="">
                <?=\Local\Tools::getSvg('minus')?>
                <span class="visually-hidden">Убрать товар</span>
            </button>
            <span class="product-buttons__count-num" data-current-count=""
                  data-step="<?=$arItem['ITEM_MEASURE_RATIOS'][$arItem['ITEM_MEASURE_RATIO_SELECTED']]['RATIO']?>"
                  data-limit="<?=$arItem['PRODUCT']['QUANTITY']?>">0</span>
            <button type="button" class="product-buttons__count-button" data-button-add="">
                <?=\Local\Tools::getSvg('plus')?>
                <span class="visually-hidden">Добавить товар</span>
            </button>
            <div class="product-buttons__count-spinner">
                <span class="spinner">
                    <span class="spinner__angle spinner__angle--first"></span>
                    <span class="spinner__angle spinner__angle--two"></span>
                    <span class="spinner__angle spinner__angle--three"></span>
                    <span class="spinner__angle"></span>
                </span>
            </div>
        </div>
    </div>
</div>

<? if (false): // Блок "Предложить свою цену" временно скрываем?>
    <button class="btn btn-custom btn-custom--ss btn-custom--outline-white d-lg-block d-none"
            type="button">
        <span>Предложить свою цену</span>
    </button>
<? endif; ?>