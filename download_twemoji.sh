#!/bin/bash

# Script: Download e instale emojis Twemoji para o projeto Laravel Insights
# 
# Uso: bash download_twemoji.sh
# 
# Este script baixa emojis PNG do repositório Twemoji (MIT License)
# e os organiza em: resources/emojis/twemoji/
#
# Emojis mais utilizados em PDFs são baixados por padrão.
# Customize a lista EMOJI_CODEPOINTS conforme necessário.

# Não use 'set -e' para permitir que o script continue mesmo com algumas falhas

# Configuração
TWEMOJI_VERSION="latest"
TWEMOJI_PNG_URL="https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/72x72"
TWEMOJI_SVG_URL="https://cdn.jsdelivr.net/gh/twitter/twemoji@14/assets/svg"
TARGET_DIR="resources/emojis/twemoji"
PROJECT_ROOT=$(pwd)

# Emojis comuns para usar em PDFs (unicode codepoints)
# Seleção abrangente com 40+ emojis úteis
EMOJI_CODEPOINTS=(
    # PDF Icons (5)
    "2139"      # ℹ️ Info
    "26a0"      # ⚠️ Warning
    "1f534"     # 🔴 Red circle
    "1f535"     # 🔵 Blue circle
    "2705"      # ✅ Check Mark
    
    # Status (7)
    "2714"      # ✔️ Checkmark
    "274c"      # ❌ Cross
    "274e"      # ❌ Cross Mark
    "2716"      # ✖️ Heavy Multiplication X
    "1f504"     # 🔄 Repeat
    "1f6a8"     # 🚨 Alert/Siren
    "1f6ab"     # 🚫 No Entry
    
    # Actions (8)
    "1f525"     # 🔥 Fire
    "1f4a8"     # 💨 Dashing Away
    "1f4a9"     # 💩 Pile of Poo
    "1f4a1"     # 💡 Bulb
    "1f4aa"     # 💪 Muscle
    "1f44c"     # 👌 OK Hand
    "1f44d"     # 👍 Thumbs Up
    "1f44e"     # 👎 Thumbs Down
    
    # Time (5)
    "1f550"     # 🕐 Clock
    "1f551"     # 🕑 Clock
    "1f552"     # 🕒 Clock
    "23f1"      # ⏱️ Stopwatch
    "23f0"      # ⏰ Alarm Clock
    
    # Objects (8)
    "1f4dd"     # 📝 Memo
    "1f4c1"     # 📁 Folder
    "1f4c4"     # 📄 Page
    "1f512"     # 🔒 Lock
    "1f513"     # 🔓 Unlock
    "1f50d"     # 🔍 Magnifying Glass
    "1f6e0"     # 🛠️ Tools
    "2699"      # ⚙️ Gear
    
    # Nature (4)
    "1f49a"     # 💚 Green Heart
    "1f49b"     # 💛 Yellow Heart
    "1f49c"     # 💜 Purple Heart
    "1f534"     # ❤️ Red Heart (redundant but included)
)


echo "🎨 Twemoji Downloader para Laravel Insights"
echo "==========================================="
echo ""
echo "Target directory: $TARGET_DIR"
echo "Emojis to download: ${#EMOJI_CODEPOINTS[@]}"
echo ""

# Criar diretório
mkdir -p "$TARGET_DIR"
echo "✓ Directory created: $TARGET_DIR"

# Baixar emojis
echo ""
echo "📥 Downloading emojis..."
downloaded=0
failed=0

for codepoint in "${EMOJI_CODEPOINTS[@]}"; do
    filename="${codepoint}.png"
    filepath="$TARGET_DIR/$filename"
    
    # URL para Twemoji 72x72 PNG
    url="${TWEMOJI_PNG_URL}/${codepoint}.png"
    
    if curl -s -f "$url" -o "$filepath" 2>/dev/null; then
        echo "  ✓ Downloaded: $codepoint"
        ((downloaded++))
    else
        # Tentar com .svg e converter (se ImageMagick disponível)
        svg_url="${TWEMOJI_SVG_URL}/${codepoint}.svg"
        if curl -s -f "$svg_url" -o "/tmp/${codepoint}.svg" 2>/dev/null; then
            if command -v convert &> /dev/null; then
                convert "/tmp/${codepoint}.svg" -resize 72x72 "$filepath"
                echo "  ✓ Converted: $codepoint (SVG → PNG)"
                ((downloaded++))
            else
                echo "  ✗ Failed: $codepoint (SVG downloaded but ImageMagick not installed)"
                ((failed++))
            fi
            rm -f "/tmp/${codepoint}.svg"
        else
            echo "  ✗ Failed: $codepoint"
            ((failed++))
        fi
    fi
done

echo ""
echo "📊 Summary"
echo "==========="
echo "Downloaded: $downloaded/${#EMOJI_CODEPOINTS[@]}"
echo "Failed: $failed"
echo ""

if [ $downloaded -gt 0 ]; then
    echo "✅ Installation successful!"
    echo ""
    echo "📝 Next steps:"
    echo "  1. Verify emojis in: $TARGET_DIR"
    echo "  2. Use in code with EmojiPath helper:"
    echo "     \$uri = EmojiPath::getUri('2714'); // ✔️"
    echo "     <img src=\"{{ \$uri }}\" width=\"16\" height=\"16\" />"
    echo ""
else
    echo "❌ Installation failed. Please check:"
    echo "  - Internet connection"
    echo "  - Twemoji URL availability"
    echo "  - Directory permissions"
    exit 1
fi

exit 0

# Adicionar ícones adicionais do PDF que não estavam na lista original
EXTRA_EMOJIS=(
    "1f534"     # 🔴 Red circle (red_dot)
    "1f535"     # 🔵 Blue circle (blue_dot) 
)

echo ""
echo "📥 Downloading additional PDF icons..."
for codepoint in "${EXTRA_EMOJIS[@]}"; do
    filename="${codepoint}.png"
    filepath="$TARGET_DIR/$filename"
    
    url="${TWEMOJI_PNG_URL}/${codepoint}.png"
    
    if curl -s -f "$url" -o "$filepath" 2>/dev/null; then
        echo "  ✓ Downloaded: $codepoint"
    else
        echo "  ℹ️ Skipped: $codepoint (optional)"
    fi
done

echo ""
echo "✅ All emojis ready for use!"
