#!/bin/bash

# Function to translate a file
translate_file() {
    local file="$1"
    echo "Processing: $file"
    
    # Create temporary file
    tmp_file="${file}.tmp"
    
    # Apply all translations using sed
    sed -i 's/Вычисляемое поле: Категоризация клиентов по сумме покупок/Computed field: Customer categorization by total purchase amount/g' "$file"
    sed -i 's/Определяет уровень клиента на основе общей суммы покупок/Determines customer tier based on total purchases/g' "$file"
    sed -i 's/Используется для сегментации клиентов, персонализации предложений,/Used for customer segmentation, personalized offers,/g' "$file"
    sed -i 's/VIP-обслуживания и маркетинговых кампаний\./VIP services, and marketing campaigns./g' "$file"
    sed -i 's/Вычислить категорию клиента/Calculate customer tier/g' "$file"
    sed -i 's/Данные строки грида (клиента)/Grid row data (customer)/g' "$file"
    sed -i "s/Категория: 'VIP', 'Regular' или 'New'/Tier: 'VIP', 'Regular' or 'New'/g" "$file"
    sed -i 's/API контроллер для управления адресами клиентов (Manager API)/API controller for managing customer addresses (Manager API)/g' "$file"
    sed -i 's/Получить список адресов клиента/Get list of customer addresses/g' "$file"
    sed -i 's/URL параметры/URL parameters/g' "$file"
    sed -i 's/Проверяем существование клиента/Check if customer exists/g' "$file"
    sed -i 's/Создать новый адрес/Create new address/g' "$file"
    sed -i 's/Данные адреса/Address data/g' "$file"
    sed -i 's/Заполняем поля/Fill fields/g' "$file"
    sed -i 's/Генерируем hash для адреса/Generate address hash/g' "$file"
    sed -i 's/Обновить адрес/Update address/g' "$file"
    sed -i 's/Данные для обновления/Data for update/g' "$file"
    sed -i 's/Обновляем поля/Update fields/g' "$file"
    sed -i 's/Обновляем hash/Update hash/g' "$file"
    sed -i 's/Удалить адрес/Delete address/g' "$file"
    sed -i 's/Форматировать объект адреса для API ответа/Format address object for API response/g' "$file"
    sed -i 's/Форматированный адрес для отображения/Formatted address for display/g' "$file"
    sed -i 's/Генерировать hash адреса для дедупликации/Generate address hash for deduplication/g' "$file"
    sed -i 's/Форматировать адрес в строку/Format address to string/g' "$file"
    
    echo "  Done: $file"
}

# Export function for use with find -exec
export -f translate_file

# Find all PHP files with Cyrillic and translate them
grep -rl '[а-яА-ЯёЁ]' core/components/minishop3/src/ --include="*.php" | while read file; do
    translate_file "$file"
done

echo "Translation complete!"
