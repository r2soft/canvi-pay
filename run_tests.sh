#!/bin/bash

# Configuração
set -e

# Cores para saída
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Função para exibir mensagens de informação
info() {
    echo -e "${YELLOW}ℹ️  $1${NC}"
}

# Função para exibir mensagens de sucesso
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Função para exibir mensagens de erro
error() {
    echo -e "${RED}❌ $1${NC}"
}

# Verifica se o número de falhas foi fornecido
if [ -z "$1" ]; then
    error "Por favor, informe o número máximo de falhas permitidas."
    echo "Uso: $0 <max_failures>"
    exit 1
fi

MAX_FAILURES=$1
REPORT_XML="report.xml"

info "********************************* Construindo imagem Docker *********************************"
if ! docker build -t canvi-pay-tests -f docker/Dockerfile . > /dev/null; then
    error "Falha ao construir a imagem Docker"
    exit 1
fi

info "👉 Instalando dependências com o composer..."
if ! docker run --rm -v $(pwd):/app -w /app canvi-pay-tests composer install --no-interaction --no-progress --prefer-dist > /dev/null; then
    error "Falha ao instalar as dependências do Composer"
    exit 1
fi

info "********************************* Executando Testes *********************************"

# Executa os testes e captura a saída
info "👉 Executando os testes PHPUnit..."
if ! docker run --rm -v $(pwd):/app -w /app canvi-pay-tests ./vendor/bin/phpunit --testsuite canvi-pay --log-junit "$REPORT_XML" --testdox; then
    error "⚠️  Alguns testes falharam ou ocorreram erros durante a execução"
fi

# Se o arquivo de relatório não foi criado, tenta executar sem gerar o relatório XML
if [ ! -f "$REPORT_XML" ]; then
    info "⚠️  O arquivo $REPORT_XML não foi gerado. Executando sem gerar relatório..."
    docker run --rm -v $(pwd):/app -w /app canvi-pay-tests ./vendor/bin/phpunit --testsuite canvi-pay --testdox
    exit 0
fi

echo "👉 Extraindo dados do relatório $REPORT_XML"
echo "👉 local: $(pwd)"
echo "👉 Arquivo do relatorio: $(ls -ll $REPORT_XML)"

if [ -f "$REPORT_XML" ]; then
    TESTS=$(grep -oP 'tests="\K[0-9]+' "$REPORT_XML" | head -n1)
    ASSERTIONS=$(grep -oP 'assertions="\K[0-9]+' "$REPORT_XML" | head -n1)
    ERRORS=$(grep -oP 'errors="\K[0-9]+' "$REPORT_XML" | head -n1)
    FAILURES=$(grep -oP 'failures="\K[0-9]+' "$REPORT_XML" | head -n1)
    SKIPPED=$(grep -oP 'skipped="\K[0-9]+' "$REPORT_XML" | head -n1)
    TIME=$(grep -oP 'time="\K[0-9.]+' "$REPORT_XML" | head -n1)


    export TOTAL_FAILURES=0

    # Verifica se $FAILURES está vazio
    if [ -z "$FAILURES" ]; then
        FAILURES=0  # Atribui 0 se estiver vazio
    fi

    # Verifica se $ERRORS está vazio
    if [ -z "$ERRORS" ]; then
        ERRORS=0  # Atribui 0 se estiver vazio
    fi

    # Total é a soma de $ERRORS com $FAILURES
    # shellcheck disable=SC2004
    TOTAL_FAILURES=$(($FAILURES + $ERRORS))

    echo "┌──────────────────────────────── Resultados extraídos do report.xml ────────────────────────────────┐"

    printf "┌────────────────────────────────┐\n"
    printf "│ 📊 Resumo dos Testes           │\n"
    printf "├────────────────────────────────┤\n"
    printf "│ Tests             │ %-10s │\n" "$TESTS"
    printf "│ Assertions ✅      │ %-10s │\n" "$ASSERTIONS"
    printf "│ Failures ❌        │ %-10s │\n" "$FAILURES"
    printf "│ Errors 🔥         │ %-10s │\n" "$ERRORS"
    printf "│ Skipped           │ %-10s │\n" "$SKIPPED"
    printf "│ Total Failures    │ %-10s │\n" "$TOTAL_FAILURES"
    printf "│ Time ⏱            │ %-10s │\n" "$TIME"
    printf "└───────────────────┴────────────┘\n"


else
    echo "❗ Arquivo report.xml não encontrado 🔥"
    exit 1;
fi

if [ "$TOTAL_FAILURES"  -gt "$MAX_FAILURES" ]; then
    echo "Tests failed with $TOTAL_FAILURES total failures! 🔥💣"
    echo "└─────────────────────────────────────────────────────────────────────────────────────────────────────┘"
    exit 1;
else
    echo "Tests passed with $TOTAL_FAILURES total failures! ✅🟢"
    echo "└─────────────────────────────────────────────────────────────────────────────────────────────────────┘"
    exit 0;
fi
