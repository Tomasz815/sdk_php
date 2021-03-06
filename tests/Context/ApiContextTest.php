<?php
namespace bunq\test\Context;

use bunq\Context\ApiContext;
use bunq\Context\BunqContext;
use bunq\Context\SessionContext;
use bunq\Exception\BunqException;
use bunq\test\BunqSdkTestBase;
use bunq\Util\BunqEnumApiEnvironmentType;
use DateTime;

/**
 * Tests:
 * ApiContext
 * BunqContext
 */
class ApiContextTest extends BunqSdkTestBase
{
    /**
     * Path to a temporary context file.
     */
    const CONTEXT_FILE_PATH_TEST = __DIR__ . '/context-save-restore-test.conf';

    /**
     * String format constants.
     */
    const STRING_EMPTY = '';

    /**
     * Exception message constants.
     */
    const EXPECTED_EXCEPTION_MESSAGE = '"" can not be used as a device description, must be a non empty string.';

    /**
     */
    const DATE_TIME_INTERVAL_ONE_YEAR = 'P1Y';

    /**
     */
    public function testApiContextSerializeDeserialize()
    {
        $apiContextJson = BunqContext::getApiContext()->toJson();
        $apiContextDeSerialised = ApiContext::fromJson($apiContextJson);

        static::assertEquals($apiContextJson, $apiContextDeSerialised->toJson());
    }

    /**
     */
    public function testApiContextSaveRestore()
    {
        $apiContextJson = BunqContext::getApiContext()->toJson();
        BunqContext::getApiContext()->save(self::CONTEXT_FILE_PATH_TEST);
        $apiContextRestored = ApiContext::restore(self::CONTEXT_FILE_PATH_TEST);

        static::assertEquals($apiContextJson, $apiContextRestored->toJson());
    }

    /**
     */
    public function testAutoUpdateBunqContext()
    {
        $apiContext = BunqContext::getApiContext();

        $contextJson = json_decode($apiContext->toJson(), true);
        $expireTime =
            DateTime::createFromFormat(
                SessionContext::FORMAT_MICRO_TIME,
                $contextJson[ApiContext::FIELD_SESSION_CONTEXT][SessionContext::FIELD_EXPIRY_TIME]
            );
        $expireTime->sub(new \DateInterval(self::DATE_TIME_INTERVAL_ONE_YEAR));
        $contextJson[ApiContext::FIELD_SESSION_CONTEXT][SessionContext::FIELD_EXPIRY_TIME] =
            $expireTime->format(SessionContext::FORMAT_MICRO_TIME);

        $expiredApiContext = ApiContext::fromJson(json_encode($contextJson));

        BunqContext::updateApiContext(clone $expiredApiContext);

        static::assertEquals($expiredApiContext, BunqContext::getApiContext());

        BunqContext::getUserContext()->refreshUserContext();

        static::assertNotEquals($expiredApiContext, BunqContext::getApiContext());
        static::assertNotEquals(
            $expiredApiContext->getSessionContext()->getExpiryTime(),
            BunqContext::getApiContext()->getSessionContext()->getExpiryTime()
        );
        static::assertFalse(BunqContext::getApiContext()->ensureSessionActive());
    }

    /**
     */
    public function testCreateAdiContextWithInvalidDescription()
    {
        $this->expectException(BunqException::class);
        $this->expectExceptionMessage(self::EXPECTED_EXCEPTION_MESSAGE);
        ApiContext::create(BunqEnumApiEnvironmentType::SANDBOX(), self::STRING_EMPTY, false);
    }
}
