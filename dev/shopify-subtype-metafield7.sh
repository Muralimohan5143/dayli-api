#!/usr/bin/env bash
set -euo pipefail

SHOP="ce1cd1.myshopify.com"      # e.g. ce1cd1.myshopify.com
TOKEN="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"        # e.g. shpat_xxx
VERSION="2025-01"

# ------- Allowed values (one per line) -------
VALUES="$(cat <<'EOF'
groceries_other
bakery_other
puja_samagri
milk
marine_fish
loose_flowers
vegetables
dry_fruits
toiletries_and_detergents
edible_oils
hot_drink
leafy_veg
kitchen
imported_fruits
diabetic_friendly_veg_pack
hot_drinks
spices
cereals_and_pulses
south_indian_snacks
ghee
garlands
puffs
soft_drink
cakes_and_pastries
seasonal_fruits
fruits_other
bread_and_buns
north_indian_snacks
biscuits_and_chips
soft_drinks
fruit_juice
cheese
chaats_other
prawns
diabetic_friendly_fruit_pack
paneer
energy_drink
smoothie
kitchen_other
vegetable_juice
freshwater_fish
everyday_fruits
curd
regular_sweets
north_indian_chaats
crabs
sweets_other
dry_fruit_sweets
oysters
nonveg_snacks
mutton
EOF
)"

JSON_VALUES=$(printf '%s' "$VALUES" | jq -R -s -c 'split("\n")|map(select(length>0))')

GRAPHQL_ENDPOINT="https://${SHOP}/admin/api/${VERSION}/graphql.json"
HDR=(-H "X-Shopify-Access-Token: ${TOKEN}" -H "Content-Type: application/json")

echo "👉 Looking up metafield definition PRODUCT custom.subtype ..."

LOOKUP_PAYLOAD=$(jq -n --arg ns "custom" --arg key "subtype" '
{
  query: "query defs($ns:String!, $key:String!){ metafieldDefinitions(first:1, ownerType: PRODUCT, namespace:$ns, key:$key){ edges{ node{ id name key namespace type { name } ownerType validations{ name value } } } } }",
  variables: { ns: $ns, key: $key }
}')
LOOKUP_RESP=$(curl -sS -f -X POST "${GRAPHQL_ENDPOINT}" "${HDR[@]}" --data-binary "${LOOKUP_PAYLOAD}")

if [[ "$(echo "$LOOKUP_RESP" | jq '.errors? | length // 0')" != "0" ]]; then
  echo "$LOOKUP_RESP" | jq .
  echo "❌ Lookup failed."
  exit 1
fi

DEF_NODE=$(echo "$LOOKUP_RESP" | jq -r '.data.metafieldDefinitions.edges[0].node // empty')

if [[ -z "$DEF_NODE" || "$DEF_NODE" == "null" ]]; then
  echo "ℹ️  No existing definition. Creating custom.subtype ..."
  CREATE_PAYLOAD=$(jq -n \
    --arg name "Product Sub Type" \
    --arg ns "custom" \
    --arg key "subtype" \
    --arg type "single_line_text_field" \
    --arg values "$JSON_VALUES" '
{
  query: "mutation create($definition: MetafieldDefinitionInput!){ metafieldDefinitionCreate(definition:$definition){ createdDefinition{ id name key namespace type { name } ownerType validations{ name value } } userErrors{ field message code } } }",
  variables: {
    definition: {
      name: $name,
      namespace: $ns,
      key: $key,
      type: $type,
      ownerType: "PRODUCT",
      description: "Dayli normalized product sub-type",
      visibleToStorefront: true,
      validations: [ { name: \"INCLUSION\", value: $values } ]
    }
  }
}')
  CREATE_RESP=$(curl -sS -f -X POST "${GRAPHQL_ENDPOINT}" "${HDR[@]}" --data-binary "${CREATE_PAYLOAD}")

  if [[ "$(echo "$CREATE_RESP" | jq '.errors? | length // 0')" != "0" ]]; then
    echo "$CREATE_RESP" | jq .
    echo "❌ Create failed (top-level errors)."
    exit 1
  fi

  echo "$CREATE_RESP" | jq .
  ERR_COUNT=$(echo "$CREATE_RESP" | jq '[.data.metafieldDefinitionCreate.userErrors[]?] | length')
  if [[ "$ERR_COUNT" != "0" ]]; then
    echo "❌ Create returned userErrors."
    exit 1
  fi

  DEF_ID=$(echo "$CREATE_RESP" | jq -r '.data.metafieldDefinitionCreate.createdDefinition.id')
  echo "✅ Created custom.subtype metafield definition: ${DEF_ID}"
else
  echo "✔️  Found definition. Updating INCLUSION list ..."



UPDATE_PAYLOAD=$(jq -n \
  --arg ns "custom" \
  --arg key "subtype" \
  --arg values "$JSON_VALUES" '
{
  query: "mutation upd($definition: MetafieldDefinitionUpdateInput!){ metafieldDefinitionUpdate(definition:$definition){ updatedDefinition{ id name key namespace type { name } validations{ name value } } userErrors{ field message code } } }",
  variables: {
    definition: {
      namespace: $ns,
      key: $key,
      ownerType: "PRODUCT",
      validations: [ { name: "choices", value: $values } ]
    }
  }
}')

  
  
  


UPDATE_RESP=$(curl -sS -f -X POST "${GRAPHQL_ENDPOINT}" "${HDR[@]}" --data-binary "${UPDATE_PAYLOAD}")

  if [[ "$(echo "$UPDATE_RESP" | jq '.errors? | length // 0')" != "0" ]]; then
    echo "$UPDATE_RESP" | jq .
    echo "❌ Update failed (top-level errors)."
    exit 1
  fi

  echo "$UPDATE_RESP" | jq .
  ERR_COUNT=$(echo "$UPDATE_RESP" | jq '[.data.metafieldDefinitionUpdate.userErrors[]?] | length')
  if [[ "$ERR_COUNT" != "0" ]]; then
    echo "❌ Update returned userErrors."
    exit 1
  fi

  echo "✅ Updated custom.subtype metafield definition."
fi

echo "🎉 Done."

