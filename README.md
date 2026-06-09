# Envios da Olist — Módulo Magento 2

Módulo oficial de fretes para Magento 2. Exibe as opções de frete do Envios da Olist diretamente no checkout da loja, consumindo a API de cotação em tempo real.

---

## Requisitos

- PHP 8.1 ou superior
- Magento 2.4.x
- Extensão PHP `ext-curl`
- Conta ativa no [Envios da Olist](https://envios.olist.com) com token de integração gerado

---

## Instalação via Composer

```bash
composer require olist/module-envios
php bin/magento module:enable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

## Instalação manual

1. Faça o download ou clone este repositório
2. Copie a pasta para `app/code/Olist/Envios/` na raiz da sua instalação Magento
3. Execute os comandos abaixo:

```bash
php bin/magento module:enable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

---

## Configuração

Acesse **Lojas → Configuração → Vendas → Métodos de Entrega → Envios da Olist** e preencha os campos:

| Campo | Descrição |
|---|---|
| **Habilitado** | Ativa ou desativa o método de entrega no checkout |
| **Título** | Nome exibido ao comprador (padrão: "Envios da Olist") |
| **Token de integração** | UUID do token obtido no painel do Envios da Olist |
| **URL da API** | URL base da API — altere apenas para apontar a um ambiente de testes |
| **Mensagem de erro** | Texto exibido ao comprador quando o cálculo de frete falhar |
| **Modo debug** | Registra requisições e respostas em `var/log/system.log` |

Salve e limpe o cache:

```bash
php bin/magento cache:flush
```

---

## Dimensões de produtos

Na versão atual, as dimensões individuais dos produtos (altura, largura, comprimento) são determinadas pelo servidor com base no peso total do carrinho. Não é necessário configurar medidas nos produtos da loja.

Caso a API não consiga calcular o frete a partir dos dados do pedido, são aplicados valores padrão de **10 × 15 × 20 cm** automaticamente.

---

## Unidade de peso

O módulo lê a unidade de peso configurada na loja em **Lojas → Configuração → Geral → Opções de Localidade → Unidade de Peso** e converte automaticamente para quilogramas antes de enviar à API.

Unidades suportadas: `kg` (sem conversão) e `lbs` (multiplicado por 0,453592).

---

## Cache

As cotações são armazenadas em cache por **5 minutos** com base no conteúdo do carrinho e no CEP de destino. Para limpar o cache de cotações manualmente:

```bash
php bin/magento cache:clean olist_envios_quote
```

---

## Debug

Ative o **Modo debug** nas configurações. As requisições e respostas da API serão registradas em `var/log/system.log` com o prefixo `[Olist Envios]`.

---

## Desinstalação

```bash
php bin/magento module:disable Olist_Envios
php bin/magento setup:upgrade
php bin/magento cache:flush
```

Se instalado via Composer:

```bash
composer remove olist/module-envios
```
