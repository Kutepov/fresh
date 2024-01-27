<?php declare(strict_types=1);

namespace common\services;

use common\components\caching\Cache;
use common\models\SourceUrl;
use yii\caching\TagDependency;
use yii\helpers\ArrayHelper;

class SourcesUrlsService
{
    /** @var SourcesService */
    private $sourcesService;

    public function __construct()
    {
        $this->sourcesService = \Yii::$container->get(SourcesService::class);
    }

    /**
     * @param array $sourcesUrlsIds
     * @param string|null $forCountry
     * @param string|null $forLanguage
     * @return array|bool
     */
    public function getFilteredSourcesUrlsIds(array $sourcesUrlsIds, ?string $forCountry = null, ?string $forLanguage = null)
    {
        $sourcesUrlsIds = array_values($sourcesUrlsIds);
        $enabledSourcesIds = $this->getEnabledSourcesUrlsIds($forCountry, $forLanguage);

        if (!count($enabledSourcesIds)) {
            return false;
        }

        if (!count($sourcesUrlsIds)) {
            return $enabledSourcesIds;
        }

        return array_values(array_intersect($sourcesUrlsIds, $enabledSourcesIds)) ?: false;
    }

    /**
     * @param string|null $forCountry
     * @param string|null $forLanguage
     * @return array
     */
    public function getEnabledSourcesUrlsIds(?string $forCountry = null, ?string $forLanguage = null): array
    {
        $enabledSourcesUrls = $this->getEnabledSourcesUrls($forCountry, $forLanguage);

        return ArrayHelper::getColumn($enabledSourcesUrls, 'id');
    }

    public function getEnabledSourcesUrls(?string $forCountry = null, ?string $forLanguage = null): array
    {
        return SourceUrl::find()
            ->enabled()
//            ->byCountry($forCountry, false)
//            ->byLanguage($forLanguage, false)
            ->orderBy(['name' => SORT_ASC])
            ->cache(
                Cache::DURATION_SOURCES_LIST,
                new TagDependency([
                    'tags' => Cache::TAG_SOURCES_LIST
                ])
            )
            ->all();
    }


    private const MAPPING = [
        1130 => 936,
        1132 => 977,
        1138 => 983,
        1137 => 982,
        1134 => 979,
        1136 => 981,
        1133 => 978,
        1139 => 1032,
        1135 => 980,
        1140 => 1033,
        1141 => 1034,
        1143 => 1036,
        1144 => null,
        1142 => 1035,
        1158 => 1117,
        1160 => 1115,
        1159 => 1116,
        1163 => 1118,
        1165 => 1111,
        1164 => 1112,
        1161 => 1114,
        1162 => 1113,
        1166 => 1110,
        1186 => 991,
        1236 => 1005,
        1238 => 1014,
        1241 => 1006,
        1242 => 1010,
        1239 => 1004,
        1240 => 1008,
        1237 => 1009,
        1243 => 1003,
        1244 => 1007,
        1245 => 1013,
        1246 => 1012,
        1247 => 1011,
        1224 => 962,
        1225 => 970,
        1226 => 967,
        1228 => 969,
        1230 => 968,
        1234 => 974,
        1227 => 964,
        1229 => 963,
        1231 => 966,
        1232 => 971,
        1233 => 973,
        1235 => 965,
        1170 => 941,
        1168 => 939,
        1167 => 938,
        1169 => 940,
        1172 => null,
        1171 => 942,
        1148 => 1127,
        1147 => null,
        1145 => null,
        1146 => null,
        1251 => 1046,
        1149 => 1047,
        1150 => 1044,
        1156 => 1038,
        1152 => 1042,
        1151 => 1043,
        1153 => 1041,
        1250 => 1045,
        1155 => 1039,
        1154 => 1040,
        1157 => 1037,
        1198 => 1093,
        1201 => 1089,
        1197 => 1090,
        1199 => 1088,
        1200 => null,
        1202 => 1091,
        1194 => 1000,
        1196 => 1002,
        1193 => 999,
        1191 => 997,
        1189 => 995,
        1190 => 996,
        1188 => 994,
        1187 => 993,
        1195 => 1001,
        1192 => 998,
        1175 => 1067,
        1174 => 1063,
        1179 => 1061,
        1182 => 1060,
        1176 => 1062,
        1177 => 1064,
        1178 => 1069,
        1183 => 1071,
        1184 => 1066,
        1173 => 1070,
        1180 => 1068,
        1181 => 1065,
        1185 => 1059
    ];

    public function convertUaRuSourcesUrlsIdsToUkIfNeeded(array $sourcesUrlsIds): array
    {
        $result = [];

        foreach ($sourcesUrlsIds as $sourceUrlId) {
            if (isset(self::MAPPING[$sourceUrlId]) && self::MAPPING[$sourceUrlId]) {
                $result[] = self::MAPPING[$sourceUrlId];
            } else {
                $result[] = $sourceUrlId;
            }
        }

        return $result;
    }

    public function convertUaRuSourcesUrlIdToUkIfNeeded($sourceUrlId)
    {
        if ($converted = $this->convertUaRuSourcesUrlsIdsToUkIfNeeded([$sourceUrlId])) {
            return reset($converted);
        }

        return $sourceUrlId;
    }

    public function getFallbackRuSourceUrlIdForUkSourceUrl($ukSourceUrlId)
    {
        if (($foundedKey = array_search($ukSourceUrlId, self::MAPPING, true)) !== false) {
            return $foundedKey;
        }

        return null;
    }
}