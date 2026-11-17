var __create = Object.create;
var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __getProtoOf = Object.getPrototypeOf;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __export = (target, all) => {
  for (var name in all)
    __defProp(target, name, { get: all[name], enumerable: true });
};
var __copyProps = (to, from, except, desc) => {
  if (from && typeof from === "object" || typeof from === "function") {
    for (let key of __getOwnPropNames(from))
      if (!__hasOwnProp.call(to, key) && key !== except)
        __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
  }
  return to;
};
var __toESM = (mod2, isNodeMode, target) => (target = mod2 != null ? __create(__getProtoOf(mod2)) : {}, __copyProps(
  // If the importer is in node compatibility mode or this is not an ESM
  // file that has been converted to a CommonJS file using a Babel-
  // compatible transform (i.e. "__esModule" has not been set), then set
  // "default" to the CommonJS "module.exports" for node compatibility.
  isNodeMode || !mod2 || !mod2.__esModule ? __defProp(target, "default", { value: mod2, enumerable: true }) : target,
  mod2
));

// node_modules/ethers/lib.esm/_version.js
var version = "6.17.0";

// node_modules/ethers/lib.esm/utils/properties.js
function checkType(value, type, name) {
  const types = type.split("|").map((t) => t.trim());
  for (let i = 0; i < types.length; i++) {
    switch (type) {
      case "any":
        return;
      case "bigint":
      case "boolean":
      case "number":
      case "string":
        if (typeof value === type) {
          return;
        }
    }
  }
  const error = new Error(`invalid value for type ${type}`);
  error.code = "INVALID_ARGUMENT";
  error.argument = `value.${name}`;
  error.value = value;
  throw error;
}
async function resolveProperties(value) {
  const keys = Object.keys(value);
  const results = await Promise.all(keys.map((k) => Promise.resolve(value[k])));
  return results.reduce((accum, v, index) => {
    accum[keys[index]] = v;
    return accum;
  }, {});
}
function defineProperties(target, values, types) {
  for (let key in values) {
    let value = values[key];
    const type = types ? types[key] : null;
    if (type) {
      checkType(value, type, key);
    }
    Object.defineProperty(target, key, { enumerable: true, value, writable: false });
  }
}

// node_modules/ethers/lib.esm/utils/errors.js
function stringify(value, seen) {
  if (value == null) {
    return "null";
  }
  if (seen == null) {
    seen = /* @__PURE__ */ new Set();
  }
  if (typeof value === "object") {
    if (seen.has(value)) {
      return "[Circular]";
    }
    seen.add(value);
  }
  if (Array.isArray(value)) {
    return "[ " + value.map((v) => stringify(v, seen)).join(", ") + " ]";
  }
  if (value instanceof Uint8Array) {
    const HEX = "0123456789abcdef";
    let result = "0x";
    for (let i = 0; i < value.length; i++) {
      result += HEX[value[i] >> 4];
      result += HEX[value[i] & 15];
    }
    return result;
  }
  if (typeof value === "object" && typeof value.toJSON === "function") {
    return stringify(value.toJSON(), seen);
  }
  switch (typeof value) {
    case "boolean":
    case "number":
    case "symbol":
      return value.toString();
    case "bigint":
      return BigInt(value).toString();
    case "string":
      return JSON.stringify(value);
    case "object": {
      const keys = Object.keys(value);
      keys.sort();
      return "{ " + keys.map((k) => `${stringify(k, seen)}: ${stringify(value[k], seen)}`).join(", ") + " }";
    }
  }
  return `[ COULD NOT SERIALIZE ]`;
}
function makeError(message, code, info) {
  let shortMessage = message;
  {
    const details = [];
    if (info) {
      if ("message" in info || "code" in info || "name" in info) {
        throw new Error(`value will overwrite populated values: ${stringify(info)}`);
      }
      for (const key in info) {
        if (key === "shortMessage") {
          continue;
        }
        const value = info[key];
        details.push(key + "=" + stringify(value));
      }
    }
    details.push(`code=${code}`);
    details.push(`version=${version}`);
    if (details.length) {
      message += " (" + details.join(", ") + ")";
    }
  }
  let error;
  switch (code) {
    case "INVALID_ARGUMENT":
      error = new TypeError(message);
      break;
    case "NUMERIC_FAULT":
    case "BUFFER_OVERRUN":
      error = new RangeError(message);
      break;
    default:
      error = new Error(message);
  }
  defineProperties(error, { code });
  if (info) {
    Object.assign(error, info);
  }
  if (error.shortMessage == null) {
    defineProperties(error, { shortMessage });
  }
  return error;
}
function assert(check, message, code, info) {
  if (!check) {
    throw makeError(message, code, info);
  }
}
function assertArgument(check, message, name, value) {
  assert(check, message, "INVALID_ARGUMENT", { argument: name, value });
}
var _normalizeForms = ["NFD", "NFC", "NFKD", "NFKC"].reduce((accum, form) => {
  try {
    if ("test".normalize(form) !== "test") {
      throw new Error("bad");
    }
    ;
    if (form === "NFD") {
      const check = String.fromCharCode(233).normalize("NFD");
      const expected = String.fromCharCode(101, 769);
      if (check !== expected) {
        throw new Error("broken");
      }
    }
    accum.push(form);
  } catch (error) {
  }
  return accum;
}, []);
function assertNormalize(form) {
  assert(_normalizeForms.indexOf(form) >= 0, "platform missing String.prototype.normalize", "UNSUPPORTED_OPERATION", {
    operation: "String.prototype.normalize",
    info: { form }
  });
}
function assertPrivate(givenGuard, guard, className) {
  if (className == null) {
    className = "";
  }
  if (givenGuard !== guard) {
    let method = className, operation = "new";
    if (className) {
      method += ".";
      operation += " " + className;
    }
    assert(false, `private constructor; use ${method}from* methods`, "UNSUPPORTED_OPERATION", {
      operation
    });
  }
}

// node_modules/ethers/lib.esm/utils/data.js
function _getBytes(value, name, copy) {
  if (value instanceof Uint8Array) {
    if (copy) {
      return new Uint8Array(value);
    }
    return value;
  }
  if (typeof value === "string" && value.length % 2 === 0 && value.match(/^0x[0-9a-f]*$/i)) {
    const result = new Uint8Array((value.length - 2) / 2);
    let offset = 2;
    for (let i = 0; i < result.length; i++) {
      result[i] = parseInt(value.substring(offset, offset + 2), 16);
      offset += 2;
    }
    return result;
  }
  assertArgument(false, "invalid BytesLike value", name || "value", value);
}
function getBytes(value, name) {
  return _getBytes(value, name, false);
}
function getBytesCopy(value, name) {
  return _getBytes(value, name, true);
}
function isHexString(value, length) {
  if (typeof value !== "string" || !value.match(/^0x[0-9A-Fa-f]*$/)) {
    return false;
  }
  if (typeof length === "number" && value.length !== 2 + 2 * length) {
    return false;
  }
  if (length === true && value.length % 2 !== 0) {
    return false;
  }
  return true;
}
function isBytesLike(value) {
  return isHexString(value, true) || value instanceof Uint8Array;
}
var HexCharacters = "0123456789abcdef";
function hexlify(data4) {
  const bytes2 = getBytes(data4);
  let result = "0x";
  for (let i = 0; i < bytes2.length; i++) {
    const v = bytes2[i];
    result += HexCharacters[(v & 240) >> 4] + HexCharacters[v & 15];
  }
  return result;
}
function concat(datas) {
  return "0x" + datas.map((d) => hexlify(d).substring(2)).join("");
}
function dataLength(data4) {
  if (isHexString(data4, true)) {
    return (data4.length - 2) / 2;
  }
  return getBytes(data4).length;
}
function dataSlice(data4, start, end) {
  const bytes2 = getBytes(data4);
  if (end != null && end > bytes2.length) {
    assert(false, "cannot slice beyond data bounds", "BUFFER_OVERRUN", {
      buffer: bytes2,
      length: bytes2.length,
      offset: end
    });
  }
  return hexlify(bytes2.slice(start == null ? 0 : start, end == null ? bytes2.length : end));
}
function zeroPad(data4, length, left) {
  const bytes2 = getBytes(data4);
  assert(length >= bytes2.length, "padding exceeds data length", "BUFFER_OVERRUN", {
    buffer: new Uint8Array(bytes2),
    length,
    offset: length + 1
  });
  const result = new Uint8Array(length);
  result.fill(0);
  if (left) {
    result.set(bytes2, length - bytes2.length);
  } else {
    result.set(bytes2, 0);
  }
  return hexlify(result);
}
function zeroPadValue(data4, length) {
  return zeroPad(data4, length, true);
}

// node_modules/ethers/lib.esm/utils/maths.js
var BN_0 = BigInt(0);
var BN_1 = BigInt(1);
var maxValue = 9007199254740991;
function toTwos(_value, _width) {
  let value = getBigInt(_value, "value");
  const width = BigInt(getNumber(_width, "width"));
  const limit = BN_1 << width - BN_1;
  if (value < BN_0) {
    value = -value;
    assert(value <= limit, "too low", "NUMERIC_FAULT", {
      operation: "toTwos",
      fault: "overflow",
      value: _value
    });
    const mask2 = (BN_1 << width) - BN_1;
    return (~value & mask2) + BN_1;
  } else {
    assert(value < limit, "too high", "NUMERIC_FAULT", {
      operation: "toTwos",
      fault: "overflow",
      value: _value
    });
  }
  return value;
}
function mask(_value, _bits) {
  const value = getUint(_value, "value");
  const bits = BigInt(getNumber(_bits, "bits"));
  return value & (BN_1 << bits) - BN_1;
}
function getBigInt(value, name) {
  switch (typeof value) {
    case "bigint":
      return value;
    case "number":
      assertArgument(Number.isInteger(value), "underflow", name || "value", value);
      assertArgument(value >= -maxValue && value <= maxValue, "overflow", name || "value", value);
      return BigInt(value);
    case "string":
      try {
        if (value === "") {
          throw new Error("empty string");
        }
        if (value[0] === "-" && value[1] !== "-") {
          return -BigInt(value.substring(1));
        }
        return BigInt(value);
      } catch (e) {
        assertArgument(false, `invalid BigNumberish string: ${e.message}`, name || "value", value);
      }
  }
  assertArgument(false, "invalid BigNumberish value", name || "value", value);
}
function getUint(value, name) {
  const result = getBigInt(value, name);
  assert(result >= BN_0, "unsigned value cannot be negative", "NUMERIC_FAULT", {
    fault: "overflow",
    operation: "getUint",
    value
  });
  return result;
}
var Nibbles = "0123456789abcdef";
function toBigInt(value) {
  if (value instanceof Uint8Array) {
    let result = "0x0";
    for (const v of value) {
      result += Nibbles[v >> 4];
      result += Nibbles[v & 15];
    }
    return BigInt(result);
  }
  return getBigInt(value);
}
function getNumber(value, name) {
  switch (typeof value) {
    case "bigint":
      assertArgument(value >= -maxValue && value <= maxValue, "overflow", name || "value", value);
      return Number(value);
    case "number":
      assertArgument(Number.isInteger(value), "underflow", name || "value", value);
      assertArgument(value >= -maxValue && value <= maxValue, "overflow", name || "value", value);
      return value;
    case "string":
      try {
        if (value === "") {
          throw new Error("empty string");
        }
        return getNumber(BigInt(value), name);
      } catch (e) {
        assertArgument(false, `invalid numeric string: ${e.message}`, name || "value", value);
      }
  }
  assertArgument(false, "invalid numeric value", name || "value", value);
}
function toBeHex(_value, _width) {
  const value = getUint(_value, "value");
  let result = value.toString(16);
  if (_width == null) {
    if (result.length % 2) {
      result = "0" + result;
    }
  } else {
    const width = getNumber(_width, "width");
    if (width === 0 && value === BN_0) {
      return "0x";
    }
    assert(width * 2 >= result.length, `value exceeds width (${width} bytes)`, "NUMERIC_FAULT", {
      operation: "toBeHex",
      fault: "overflow",
      value: _value
    });
    while (result.length < width * 2) {
      result = "0" + result;
    }
  }
  return "0x" + result;
}
function toBeArray(_value, _width) {
  const value = getUint(_value, "value");
  if (value === BN_0) {
    const width = _width != null ? getNumber(_width, "width") : 0;
    return new Uint8Array(width);
  }
  let hex2 = value.toString(16);
  if (hex2.length % 2) {
    hex2 = "0" + hex2;
  }
  if (_width != null) {
    const width = getNumber(_width, "width");
    while (hex2.length < width * 2) {
      hex2 = "00" + hex2;
    }
    assert(width * 2 === hex2.length, `value exceeds width (${width} bytes)`, "NUMERIC_FAULT", {
      operation: "toBeArray",
      fault: "overflow",
      value: _value
    });
  }
  const result = new Uint8Array(hex2.length / 2);
  for (let i = 0; i < result.length; i++) {
    const offset = i * 2;
    result[i] = parseInt(hex2.substring(offset, offset + 2), 16);
  }
  return result;
}
function toQuantity(value) {
  let result = hexlify(isBytesLike(value) ? value : toBeArray(value)).substring(2);
  while (result.startsWith("0")) {
    result = result.substring(1);
  }
  if (result === "") {
    result = "0";
  }
  return "0x" + result;
}

// node_modules/ethers/lib.esm/utils/base58.js
var Alphabet = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
var Lookup = null;
function getAlpha(letter) {
  if (Lookup == null) {
    Lookup = {};
    for (let i = 0; i < Alphabet.length; i++) {
      Lookup[Alphabet[i]] = BigInt(i);
    }
  }
  const result = Lookup[letter];
  assertArgument(result != null, `invalid base58 value`, "letter", letter);
  return result;
}
var BN_02 = BigInt(0);
var BN_58 = BigInt(58);
function encodeBase58(_value) {
  const bytes2 = getBytes(_value);
  let value = toBigInt(bytes2);
  let result = "";
  while (value) {
    result = Alphabet[Number(value % BN_58)] + result;
    value /= BN_58;
  }
  for (let i = 0; i < bytes2.length; i++) {
    if (bytes2[i]) {
      break;
    }
    result = Alphabet[0] + result;
  }
  return result;
}
function decodeBase58(value) {
  let result = BN_02;
  for (let i = 0; i < value.length; i++) {
    result *= BN_58;
    result += getAlpha(value[i]);
  }
  return result;
}

// node_modules/ethers/lib.esm/utils/utf8.js
function errorFunc(reason, offset, bytes2, output2, badCodepoint) {
  assertArgument(false, `invalid codepoint at offset ${offset}; ${reason}`, "bytes", bytes2);
}
function ignoreFunc(reason, offset, bytes2, output2, badCodepoint) {
  if (reason === "BAD_PREFIX" || reason === "UNEXPECTED_CONTINUE") {
    let i = 0;
    for (let o = offset + 1; o < bytes2.length; o++) {
      if (bytes2[o] >> 6 !== 2) {
        break;
      }
      i++;
    }
    return i;
  }
  if (reason === "OVERRUN") {
    return bytes2.length - offset - 1;
  }
  return 0;
}
function replaceFunc(reason, offset, bytes2, output2, badCodepoint) {
  if (reason === "OVERLONG") {
    assertArgument(typeof badCodepoint === "number", "invalid bad code point for replacement", "badCodepoint", badCodepoint);
    output2.push(badCodepoint);
    return 0;
  }
  output2.push(65533);
  return ignoreFunc(reason, offset, bytes2, output2, badCodepoint);
}
var Utf8ErrorFuncs = Object.freeze({
  error: errorFunc,
  ignore: ignoreFunc,
  replace: replaceFunc
});
function getUtf8CodePoints(_bytes, onError) {
  if (onError == null) {
    onError = Utf8ErrorFuncs.error;
  }
  const bytes2 = getBytes(_bytes, "bytes");
  const result = [];
  let i = 0;
  while (i < bytes2.length) {
    const c = bytes2[i++];
    if (c >> 7 === 0) {
      result.push(c);
      continue;
    }
    let extraLength = null;
    let overlongMask = null;
    if ((c & 224) === 192) {
      extraLength = 1;
      overlongMask = 127;
    } else if ((c & 240) === 224) {
      extraLength = 2;
      overlongMask = 2047;
    } else if ((c & 248) === 240) {
      extraLength = 3;
      overlongMask = 65535;
    } else {
      if ((c & 192) === 128) {
        i += onError("UNEXPECTED_CONTINUE", i - 1, bytes2, result);
      } else {
        i += onError("BAD_PREFIX", i - 1, bytes2, result);
      }
      continue;
    }
    if (i - 1 + extraLength >= bytes2.length) {
      i += onError("OVERRUN", i - 1, bytes2, result);
      continue;
    }
    let res = c & (1 << 8 - extraLength - 1) - 1;
    for (let j = 0; j < extraLength; j++) {
      let nextChar = bytes2[i];
      if ((nextChar & 192) != 128) {
        i += onError("MISSING_CONTINUE", i, bytes2, result);
        res = null;
        break;
      }
      ;
      res = res << 6 | nextChar & 63;
      i++;
    }
    if (res === null) {
      continue;
    }
    if (res > 1114111) {
      i += onError("OUT_OF_RANGE", i - 1 - extraLength, bytes2, result, res);
      continue;
    }
    if (res >= 55296 && res <= 57343) {
      i += onError("UTF16_SURROGATE", i - 1 - extraLength, bytes2, result, res);
      continue;
    }
    if (res <= overlongMask) {
      i += onError("OVERLONG", i - 1 - extraLength, bytes2, result, res);
      continue;
    }
    result.push(res);
  }
  return result;
}
function toUtf8Bytes(str, form) {
  assertArgument(typeof str === "string", "invalid string value", "str", str);
  if (form != null) {
    assertNormalize(form);
    str = str.normalize(form);
  }
  let result = [];
  for (let i = 0; i < str.length; i++) {
    const c = str.charCodeAt(i);
    if (c < 128) {
      result.push(c);
    } else if (c < 2048) {
      result.push(c >> 6 | 192);
      result.push(c & 63 | 128);
    } else if ((c & 64512) == 55296) {
      i++;
      const c2 = str.charCodeAt(i);
      assertArgument(i < str.length && (c2 & 64512) === 56320, "invalid surrogate pair", "str", str);
      const pair = 65536 + ((c & 1023) << 10) + (c2 & 1023);
      result.push(pair >> 18 | 240);
      result.push(pair >> 12 & 63 | 128);
      result.push(pair >> 6 & 63 | 128);
      result.push(pair & 63 | 128);
    } else {
      result.push(c >> 12 | 224);
      result.push(c >> 6 & 63 | 128);
      result.push(c & 63 | 128);
    }
  }
  return new Uint8Array(result);
}
function _toUtf8String(codePoints) {
  return codePoints.map((codePoint) => {
    if (codePoint <= 65535) {
      return String.fromCharCode(codePoint);
    }
    codePoint -= 65536;
    return String.fromCharCode((codePoint >> 10 & 1023) + 55296, (codePoint & 1023) + 56320);
  }).join("");
}
function toUtf8String(bytes2, onError) {
  return _toUtf8String(getUtf8CodePoints(bytes2, onError));
}

// node_modules/ethers/lib.esm/utils/rlp-decode.js
function hexlifyByte(value) {
  let result = value.toString(16);
  while (result.length < 2) {
    result = "0" + result;
  }
  return "0x" + result;
}
function unarrayifyInteger(data4, offset, length) {
  let result = 0;
  for (let i = 0; i < length; i++) {
    result = result * 256 + data4[offset + i];
  }
  return result;
}
function _decodeChildren(data4, offset, childOffset, length) {
  const result = [];
  while (childOffset < offset + 1 + length) {
    const decoded = _decode(data4, childOffset);
    result.push(decoded.result);
    childOffset += decoded.consumed;
    assert(childOffset <= offset + 1 + length, "child data too short", "BUFFER_OVERRUN", {
      buffer: data4,
      length,
      offset
    });
  }
  return { consumed: 1 + length, result };
}
function _decode(data4, offset) {
  assert(data4.length !== 0, "data too short", "BUFFER_OVERRUN", {
    buffer: data4,
    length: 0,
    offset: 1
  });
  const checkOffset = (offset2) => {
    assert(offset2 <= data4.length, "data short segment too short", "BUFFER_OVERRUN", {
      buffer: data4,
      length: data4.length,
      offset: offset2
    });
  };
  if (data4[offset] >= 248) {
    const lengthLength = data4[offset] - 247;
    checkOffset(offset + 1 + lengthLength);
    const length = unarrayifyInteger(data4, offset + 1, lengthLength);
    checkOffset(offset + 1 + lengthLength + length);
    return _decodeChildren(data4, offset, offset + 1 + lengthLength, lengthLength + length);
  } else if (data4[offset] >= 192) {
    const length = data4[offset] - 192;
    checkOffset(offset + 1 + length);
    return _decodeChildren(data4, offset, offset + 1, length);
  } else if (data4[offset] >= 184) {
    const lengthLength = data4[offset] - 183;
    checkOffset(offset + 1 + lengthLength);
    const length = unarrayifyInteger(data4, offset + 1, lengthLength);
    checkOffset(offset + 1 + lengthLength + length);
    const result = hexlify(data4.slice(offset + 1 + lengthLength, offset + 1 + lengthLength + length));
    return { consumed: 1 + lengthLength + length, result };
  } else if (data4[offset] >= 128) {
    const length = data4[offset] - 128;
    checkOffset(offset + 1 + length);
    const result = hexlify(data4.slice(offset + 1, offset + 1 + length));
    return { consumed: 1 + length, result };
  }
  return { consumed: 1, result: hexlifyByte(data4[offset]) };
}
function decodeRlp(_data) {
  const data4 = getBytes(_data, "data");
  const decoded = _decode(data4, 0);
  assertArgument(decoded.consumed === data4.length, "unexpected junk after rlp payload", "data", _data);
  return decoded.result;
}

// node_modules/ethers/lib.esm/utils/rlp-encode.js
function arrayifyInteger(value) {
  const result = [];
  while (value) {
    result.unshift(value & 255);
    value >>= 8;
  }
  return result;
}
function _encode(object) {
  if (Array.isArray(object)) {
    let payload = [];
    object.forEach(function(child) {
      payload = payload.concat(_encode(child));
    });
    if (payload.length <= 55) {
      payload.unshift(192 + payload.length);
      return payload;
    }
    const length2 = arrayifyInteger(payload.length);
    length2.unshift(247 + length2.length);
    return length2.concat(payload);
  }
  const data4 = Array.prototype.slice.call(getBytes(object, "object"));
  if (data4.length === 1 && data4[0] <= 127) {
    return data4;
  } else if (data4.length <= 55) {
    data4.unshift(128 + data4.length);
    return data4;
  }
  const length = arrayifyInteger(data4.length);
  length.unshift(183 + length.length);
  return length.concat(data4);
}
var nibbles = "0123456789abcdef";
function encodeRlp(object) {
  let result = "0x";
  for (const v of _encode(object)) {
    result += nibbles[v >> 4];
    result += nibbles[v & 15];
  }
  return result;
}

// node_modules/ethers/lib.esm/utils/uuid.js
function uuidV4(randomBytes4) {
  const bytes2 = getBytes(randomBytes4, "randomBytes");
  bytes2[6] = bytes2[6] & 15 | 64;
  bytes2[8] = bytes2[8] & 63 | 128;
  const value = hexlify(bytes2);
  return [
    value.substring(2, 10),
    value.substring(10, 14),
    value.substring(14, 18),
    value.substring(18, 22),
    value.substring(22, 34)
  ].join("-");
}

// node_modules/ethers/lib.esm/crypto/crypto.js
var import_crypto = require("crypto");

// node_modules/ethers/lib.esm/crypto/hmac.js
var locked = false;
var _computeHmac = function(algorithm, key, data4) {
  return (0, import_crypto.createHmac)(algorithm, key).update(data4).digest();
};
var __computeHmac = _computeHmac;
function computeHmac(algorithm, _key, _data) {
  const key = getBytes(_key, "key");
  const data4 = getBytes(_data, "data");
  return hexlify(__computeHmac(algorithm, key, data4));
}
computeHmac._ = _computeHmac;
computeHmac.lock = function() {
  locked = true;
};
computeHmac.register = function(func) {
  if (locked) {
    throw new Error("computeHmac is locked");
  }
  __computeHmac = func;
};
Object.freeze(computeHmac);

// node_modules/ethers/node_modules/@noble/hashes/esm/_assert.js
function number(n) {
  if (!Number.isSafeInteger(n) || n < 0)
    throw new Error(`Wrong positive integer: ${n}`);
}
function bytes(b, ...lengths) {
  if (!(b instanceof Uint8Array))
    throw new Error("Expected Uint8Array");
  if (lengths.length > 0 && !lengths.includes(b.length))
    throw new Error(`Expected Uint8Array of length ${lengths}, not of length=${b.length}`);
}
function hash(hash2) {
  if (typeof hash2 !== "function" || typeof hash2.create !== "function")
    throw new Error("Hash should be wrapped by utils.wrapConstructor");
  number(hash2.outputLen);
  number(hash2.blockLen);
}
function exists(instance, checkFinished = true) {
  if (instance.destroyed)
    throw new Error("Hash instance has been destroyed");
  if (checkFinished && instance.finished)
    throw new Error("Hash#digest() has already been called");
}
function output(out, instance) {
  bytes(out);
  const min = instance.outputLen;
  if (out.length < min) {
    throw new Error(`digestInto() expects output buffer of length at least ${min}`);
  }
}

// node_modules/ethers/node_modules/@noble/hashes/esm/_u64.js
var U32_MASK64 = /* @__PURE__ */ BigInt(2 ** 32 - 1);
var _32n = /* @__PURE__ */ BigInt(32);
function fromBig(n, le = false) {
  if (le)
    return { h: Number(n & U32_MASK64), l: Number(n >> _32n & U32_MASK64) };
  return { h: Number(n >> _32n & U32_MASK64) | 0, l: Number(n & U32_MASK64) | 0 };
}
function split(lst, le = false) {
  let Ah = new Uint32Array(lst.length);
  let Al = new Uint32Array(lst.length);
  for (let i = 0; i < lst.length; i++) {
    const { h, l } = fromBig(lst[i], le);
    [Ah[i], Al[i]] = [h, l];
  }
  return [Ah, Al];
}
var rotlSH = (h, l, s) => h << s | l >>> 32 - s;
var rotlSL = (h, l, s) => l << s | h >>> 32 - s;
var rotlBH = (h, l, s) => l << s - 32 | h >>> 64 - s;
var rotlBL = (h, l, s) => h << s - 32 | l >>> 64 - s;

// node_modules/ethers/node_modules/@noble/hashes/esm/cryptoNode.js
var nc = __toESM(require("node:crypto"), 1);
var crypto = nc && typeof nc === "object" && "webcrypto" in nc ? nc.webcrypto : void 0;

// node_modules/ethers/node_modules/@noble/hashes/esm/utils.js
var u8a = (a) => a instanceof Uint8Array;
var u32 = (arr) => new Uint32Array(arr.buffer, arr.byteOffset, Math.floor(arr.byteLength / 4));
var createView = (arr) => new DataView(arr.buffer, arr.byteOffset, arr.byteLength);
var rotr = (word, shift) => word << 32 - shift | word >>> shift;
var isLE = new Uint8Array(new Uint32Array([287454020]).buffer)[0] === 68;
if (!isLE)
  throw new Error("Non little-endian hardware is not supported");
var nextTick = async () => {
};
async function asyncLoop(iters, tick, cb) {
  let ts = Date.now();
  for (let i = 0; i < iters; i++) {
    cb(i);
    const diff = Date.now() - ts;
    if (diff >= 0 && diff < tick)
      continue;
    await nextTick();
    ts += diff;
  }
}
function utf8ToBytes(str) {
  if (typeof str !== "string")
    throw new Error(`utf8ToBytes expected string, got ${typeof str}`);
  return new Uint8Array(new TextEncoder().encode(str));
}
function toBytes(data4) {
  if (typeof data4 === "string")
    data4 = utf8ToBytes(data4);
  if (!u8a(data4))
    throw new Error(`expected Uint8Array, got ${typeof data4}`);
  return data4;
}
function concatBytes(...arrays) {
  const r = new Uint8Array(arrays.reduce((sum, a) => sum + a.length, 0));
  let pad = 0;
  arrays.forEach((a) => {
    if (!u8a(a))
      throw new Error("Uint8Array expected");
    r.set(a, pad);
    pad += a.length;
  });
  return r;
}
var Hash = class {
  // Safe version that clones internal state
  clone() {
    return this._cloneInto();
  }
};
var toStr = {}.toString;
function checkOpts(defaults, opts) {
  if (opts !== void 0 && toStr.call(opts) !== "[object Object]")
    throw new Error("Options should be object or undefined");
  const merged = Object.assign(defaults, opts);
  return merged;
}
function wrapConstructor(hashCons) {
  const hashC = (msg) => hashCons().update(toBytes(msg)).digest();
  const tmp = hashCons();
  hashC.outputLen = tmp.outputLen;
  hashC.blockLen = tmp.blockLen;
  hashC.create = () => hashCons();
  return hashC;
}
function wrapXOFConstructorWithOpts(hashCons) {
  const hashC = (msg, opts) => hashCons(opts).update(toBytes(msg)).digest();
  const tmp = hashCons({});
  hashC.outputLen = tmp.outputLen;
  hashC.blockLen = tmp.blockLen;
  hashC.create = (opts) => hashCons(opts);
  return hashC;
}
function randomBytes2(bytesLength = 32) {
  if (crypto && typeof crypto.getRandomValues === "function") {
    return crypto.getRandomValues(new Uint8Array(bytesLength));
  }
  throw new Error("crypto.getRandomValues must be defined");
}

// node_modules/ethers/node_modules/@noble/hashes/esm/sha3.js
var [SHA3_PI, SHA3_ROTL, _SHA3_IOTA] = [[], [], []];
var _0n = /* @__PURE__ */ BigInt(0);
var _1n = /* @__PURE__ */ BigInt(1);
var _2n = /* @__PURE__ */ BigInt(2);
var _7n = /* @__PURE__ */ BigInt(7);
var _256n = /* @__PURE__ */ BigInt(256);
var _0x71n = /* @__PURE__ */ BigInt(113);
for (let round = 0, R = _1n, x = 1, y = 0; round < 24; round++) {
  [x, y] = [y, (2 * x + 3 * y) % 5];
  SHA3_PI.push(2 * (5 * y + x));
  SHA3_ROTL.push((round + 1) * (round + 2) / 2 % 64);
  let t = _0n;
  for (let j = 0; j < 7; j++) {
    R = (R << _1n ^ (R >> _7n) * _0x71n) % _256n;
    if (R & _2n)
      t ^= _1n << (_1n << /* @__PURE__ */ BigInt(j)) - _1n;
  }
  _SHA3_IOTA.push(t);
}
var [SHA3_IOTA_H, SHA3_IOTA_L] = /* @__PURE__ */ split(_SHA3_IOTA, true);
var rotlH = (h, l, s) => s > 32 ? rotlBH(h, l, s) : rotlSH(h, l, s);
var rotlL = (h, l, s) => s > 32 ? rotlBL(h, l, s) : rotlSL(h, l, s);
function keccakP(s, rounds = 24) {
  const B = new Uint32Array(5 * 2);
  for (let round = 24 - rounds; round < 24; round++) {
    for (let x = 0; x < 10; x++)
      B[x] = s[x] ^ s[x + 10] ^ s[x + 20] ^ s[x + 30] ^ s[x + 40];
    for (let x = 0; x < 10; x += 2) {
      const idx1 = (x + 8) % 10;
      const idx0 = (x + 2) % 10;
      const B0 = B[idx0];
      const B1 = B[idx0 + 1];
      const Th = rotlH(B0, B1, 1) ^ B[idx1];
      const Tl = rotlL(B0, B1, 1) ^ B[idx1 + 1];
      for (let y = 0; y < 50; y += 10) {
        s[x + y] ^= Th;
        s[x + y + 1] ^= Tl;
      }
    }
    let curH = s[2];
    let curL = s[3];
    for (let t = 0; t < 24; t++) {
      const shift = SHA3_ROTL[t];
      const Th = rotlH(curH, curL, shift);
      const Tl = rotlL(curH, curL, shift);
      const PI = SHA3_PI[t];
      curH = s[PI];
      curL = s[PI + 1];
      s[PI] = Th;
      s[PI + 1] = Tl;
    }
    for (let y = 0; y < 50; y += 10) {
      for (let x = 0; x < 10; x++)
        B[x] = s[y + x];
      for (let x = 0; x < 10; x++)
        s[y + x] ^= ~B[(x + 2) % 10] & B[(x + 4) % 10];
    }
    s[0] ^= SHA3_IOTA_H[round];
    s[1] ^= SHA3_IOTA_L[round];
  }
  B.fill(0);
}
var Keccak = class _Keccak extends Hash {
  // NOTE: we accept arguments in bytes instead of bits here.
  constructor(blockLen, suffix, outputLen, enableXOF = false, rounds = 24) {
    super();
    this.blockLen = blockLen;
    this.suffix = suffix;
    this.outputLen = outputLen;
    this.enableXOF = enableXOF;
    this.rounds = rounds;
    this.pos = 0;
    this.posOut = 0;
    this.finished = false;
    this.destroyed = false;
    number(outputLen);
    if (0 >= this.blockLen || this.blockLen >= 200)
      throw new Error("Sha3 supports only keccak-f1600 function");
    this.state = new Uint8Array(200);
    this.state32 = u32(this.state);
  }
  keccak() {
    keccakP(this.state32, this.rounds);
    this.posOut = 0;
    this.pos = 0;
  }
  update(data4) {
    exists(this);
    const { blockLen, state } = this;
    data4 = toBytes(data4);
    const len = data4.length;
    for (let pos = 0; pos < len; ) {
      const take = Math.min(blockLen - this.pos, len - pos);
      for (let i = 0; i < take; i++)
        state[this.pos++] ^= data4[pos++];
      if (this.pos === blockLen)
        this.keccak();
    }
    return this;
  }
  finish() {
    if (this.finished)
      return;
    this.finished = true;
    const { state, suffix, pos, blockLen } = this;
    state[pos] ^= suffix;
    if ((suffix & 128) !== 0 && pos === blockLen - 1)
      this.keccak();
    state[blockLen - 1] ^= 128;
    this.keccak();
  }
  writeInto(out) {
    exists(this, false);
    bytes(out);
    this.finish();
    const bufferOut = this.state;
    const { blockLen } = this;
    for (let pos = 0, len = out.length; pos < len; ) {
      if (this.posOut >= blockLen)
        this.keccak();
      const take = Math.min(blockLen - this.posOut, len - pos);
      out.set(bufferOut.subarray(this.posOut, this.posOut + take), pos);
      this.posOut += take;
      pos += take;
    }
    return out;
  }
  xofInto(out) {
    if (!this.enableXOF)
      throw new Error("XOF is not possible for this instance");
    return this.writeInto(out);
  }
  xof(bytes2) {
    number(bytes2);
    return this.xofInto(new Uint8Array(bytes2));
  }
  digestInto(out) {
    output(out, this);
    if (this.finished)
      throw new Error("digest() was already called");
    this.writeInto(out);
    this.destroy();
    return out;
  }
  digest() {
    return this.digestInto(new Uint8Array(this.outputLen));
  }
  destroy() {
    this.destroyed = true;
    this.state.fill(0);
  }
  _cloneInto(to) {
    const { blockLen, suffix, outputLen, rounds, enableXOF } = this;
    to || (to = new _Keccak(blockLen, suffix, outputLen, enableXOF, rounds));
    to.state32.set(this.state32);
    to.pos = this.pos;
    to.posOut = this.posOut;
    to.finished = this.finished;
    to.rounds = rounds;
    to.suffix = suffix;
    to.outputLen = outputLen;
    to.enableXOF = enableXOF;
    to.destroyed = this.destroyed;
    return to;
  }
};
var gen = (suffix, blockLen, outputLen) => wrapConstructor(() => new Keccak(blockLen, suffix, outputLen));
var sha3_224 = /* @__PURE__ */ gen(6, 144, 224 / 8);
var sha3_256 = /* @__PURE__ */ gen(6, 136, 256 / 8);
var sha3_384 = /* @__PURE__ */ gen(6, 104, 384 / 8);
var sha3_512 = /* @__PURE__ */ gen(6, 72, 512 / 8);
var keccak_224 = /* @__PURE__ */ gen(1, 144, 224 / 8);
var keccak_256 = /* @__PURE__ */ gen(1, 136, 256 / 8);
var keccak_384 = /* @__PURE__ */ gen(1, 104, 384 / 8);
var keccak_512 = /* @__PURE__ */ gen(1, 72, 512 / 8);
var genShake = (suffix, blockLen, outputLen) => wrapXOFConstructorWithOpts((opts = {}) => new Keccak(blockLen, suffix, opts.dkLen === void 0 ? outputLen : opts.dkLen, true));
var shake128 = /* @__PURE__ */ genShake(31, 168, 128 / 8);
var shake256 = /* @__PURE__ */ genShake(31, 136, 256 / 8);

// node_modules/ethers/lib.esm/crypto/keccak.js
var locked2 = false;
var _keccak256 = function(data4) {
  return keccak_256(data4);
};
var __keccak256 = _keccak256;
function keccak256(_data) {
  const data4 = getBytes(_data, "data");
  return hexlify(__keccak256(data4));
}
keccak256._ = _keccak256;
keccak256.lock = function() {
  locked2 = true;
};
keccak256.register = function(func) {
  if (locked2) {
    throw new TypeError("keccak256 is locked");
  }
  __keccak256 = func;
};
Object.freeze(keccak256);

// node_modules/ethers/node_modules/@noble/hashes/esm/_sha2.js
function setBigUint64(view, byteOffset, value, isLE2) {
  if (typeof view.setBigUint64 === "function")
    return view.setBigUint64(byteOffset, value, isLE2);
  const _32n2 = BigInt(32);
  const _u32_max = BigInt(4294967295);
  const wh = Number(value >> _32n2 & _u32_max);
  const wl = Number(value & _u32_max);
  const h = isLE2 ? 4 : 0;
  const l = isLE2 ? 0 : 4;
  view.setUint32(byteOffset + h, wh, isLE2);
  view.setUint32(byteOffset + l, wl, isLE2);
}
var SHA2 = class extends Hash {
  constructor(blockLen, outputLen, padOffset, isLE2) {
    super();
    this.blockLen = blockLen;
    this.outputLen = outputLen;
    this.padOffset = padOffset;
    this.isLE = isLE2;
    this.finished = false;
    this.length = 0;
    this.pos = 0;
    this.destroyed = false;
    this.buffer = new Uint8Array(blockLen);
    this.view = createView(this.buffer);
  }
  update(data4) {
    exists(this);
    const { view, buffer, blockLen } = this;
    data4 = toBytes(data4);
    const len = data4.length;
    for (let pos = 0; pos < len; ) {
      const take = Math.min(blockLen - this.pos, len - pos);
      if (take === blockLen) {
        const dataView = createView(data4);
        for (; blockLen <= len - pos; pos += blockLen)
          this.process(dataView, pos);
        continue;
      }
      buffer.set(data4.subarray(pos, pos + take), this.pos);
      this.pos += take;
      pos += take;
      if (this.pos === blockLen) {
        this.process(view, 0);
        this.pos = 0;
      }
    }
    this.length += data4.length;
    this.roundClean();
    return this;
  }
  digestInto(out) {
    exists(this);
    output(out, this);
    this.finished = true;
    const { buffer, view, blockLen, isLE: isLE2 } = this;
    let { pos } = this;
    buffer[pos++] = 128;
    this.buffer.subarray(pos).fill(0);
    if (this.padOffset > blockLen - pos) {
      this.process(view, 0);
      pos = 0;
    }
    for (let i = pos; i < blockLen; i++)
      buffer[i] = 0;
    setBigUint64(view, blockLen - 8, BigInt(this.length * 8), isLE2);
    this.process(view, 0);
    const oview = createView(out);
    const len = this.outputLen;
    if (len % 4)
      throw new Error("_sha2: outputLen should be aligned to 32bit");
    const outLen = len / 4;
    const state = this.get();
    if (outLen > state.length)
      throw new Error("_sha2: outputLen bigger than state");
    for (let i = 0; i < outLen; i++)
      oview.setUint32(4 * i, state[i], isLE2);
  }
  digest() {
    const { buffer, outputLen } = this;
    this.digestInto(buffer);
    const res = buffer.slice(0, outputLen);
    this.destroy();
    return res;
  }
  _cloneInto(to) {
    to || (to = new this.constructor());
    to.set(...this.get());
    const { blockLen, buffer, length, finished, destroyed, pos } = this;
    to.length = length;
    to.pos = pos;
    to.finished = finished;
    to.destroyed = destroyed;
    if (length % blockLen)
      to.buffer.set(buffer);
    return to;
  }
};

// node_modules/ethers/node_modules/@noble/hashes/esm/ripemd160.js
var Rho = /* @__PURE__ */ new Uint8Array([7, 4, 13, 1, 10, 6, 15, 3, 12, 0, 9, 5, 2, 14, 11, 8]);
var Id = /* @__PURE__ */ Uint8Array.from({ length: 16 }, (_, i) => i);
var Pi = /* @__PURE__ */ Id.map((i) => (9 * i + 5) % 16);
var idxL = [Id];
var idxR = [Pi];
for (let i = 0; i < 4; i++)
  for (let j of [idxL, idxR])
    j.push(j[i].map((k) => Rho[k]));
var shifts = /* @__PURE__ */ [
  [11, 14, 15, 12, 5, 8, 7, 9, 11, 13, 14, 15, 6, 7, 9, 8],
  [12, 13, 11, 15, 6, 9, 9, 7, 12, 15, 11, 13, 7, 8, 7, 7],
  [13, 15, 14, 11, 7, 7, 6, 8, 13, 14, 13, 12, 5, 5, 6, 9],
  [14, 11, 12, 14, 8, 6, 5, 5, 15, 12, 15, 14, 9, 9, 8, 6],
  [15, 12, 13, 13, 9, 5, 8, 6, 14, 11, 12, 11, 8, 6, 5, 5]
].map((i) => new Uint8Array(i));
var shiftsL = /* @__PURE__ */ idxL.map((idx, i) => idx.map((j) => shifts[i][j]));
var shiftsR = /* @__PURE__ */ idxR.map((idx, i) => idx.map((j) => shifts[i][j]));
var Kl = /* @__PURE__ */ new Uint32Array([
  0,
  1518500249,
  1859775393,
  2400959708,
  2840853838
]);
var Kr = /* @__PURE__ */ new Uint32Array([
  1352829926,
  1548603684,
  1836072691,
  2053994217,
  0
]);
var rotl = (word, shift) => word << shift | word >>> 32 - shift;
function f(group, x, y, z) {
  if (group === 0)
    return x ^ y ^ z;
  else if (group === 1)
    return x & y | ~x & z;
  else if (group === 2)
    return (x | ~y) ^ z;
  else if (group === 3)
    return x & z | y & ~z;
  else
    return x ^ (y | ~z);
}
var BUF = /* @__PURE__ */ new Uint32Array(16);
var RIPEMD160 = class extends SHA2 {
  constructor() {
    super(64, 20, 8, true);
    this.h0 = 1732584193 | 0;
    this.h1 = 4023233417 | 0;
    this.h2 = 2562383102 | 0;
    this.h3 = 271733878 | 0;
    this.h4 = 3285377520 | 0;
  }
  get() {
    const { h0, h1, h2, h3, h4 } = this;
    return [h0, h1, h2, h3, h4];
  }
  set(h0, h1, h2, h3, h4) {
    this.h0 = h0 | 0;
    this.h1 = h1 | 0;
    this.h2 = h2 | 0;
    this.h3 = h3 | 0;
    this.h4 = h4 | 0;
  }
  process(view, offset) {
    for (let i = 0; i < 16; i++, offset += 4)
      BUF[i] = view.getUint32(offset, true);
    let al = this.h0 | 0, ar = al, bl = this.h1 | 0, br = bl, cl = this.h2 | 0, cr = cl, dl = this.h3 | 0, dr = dl, el = this.h4 | 0, er = el;
    for (let group = 0; group < 5; group++) {
      const rGroup = 4 - group;
      const hbl = Kl[group], hbr = Kr[group];
      const rl = idxL[group], rr = idxR[group];
      const sl = shiftsL[group], sr = shiftsR[group];
      for (let i = 0; i < 16; i++) {
        const tl = rotl(al + f(group, bl, cl, dl) + BUF[rl[i]] + hbl, sl[i]) + el | 0;
        al = el, el = dl, dl = rotl(cl, 10) | 0, cl = bl, bl = tl;
      }
      for (let i = 0; i < 16; i++) {
        const tr = rotl(ar + f(rGroup, br, cr, dr) + BUF[rr[i]] + hbr, sr[i]) + er | 0;
        ar = er, er = dr, dr = rotl(cr, 10) | 0, cr = br, br = tr;
      }
    }
    this.set(this.h1 + cl + dr | 0, this.h2 + dl + er | 0, this.h3 + el + ar | 0, this.h4 + al + br | 0, this.h0 + bl + cr | 0);
  }
  roundClean() {
    BUF.fill(0);
  }
  destroy() {
    this.destroyed = true;
    this.buffer.fill(0);
    this.set(0, 0, 0, 0, 0);
  }
};
var ripemd160 = /* @__PURE__ */ wrapConstructor(() => new RIPEMD160());

// node_modules/ethers/lib.esm/crypto/ripemd160.js
var locked3 = false;
var _ripemd160 = function(data4) {
  return ripemd160(data4);
};
var __ripemd160 = _ripemd160;
function ripemd1602(_data) {
  const data4 = getBytes(_data, "data");
  return hexlify(__ripemd160(data4));
}
ripemd1602._ = _ripemd160;
ripemd1602.lock = function() {
  locked3 = true;
};
ripemd1602.register = function(func) {
  if (locked3) {
    throw new TypeError("ripemd160 is locked");
  }
  __ripemd160 = func;
};
Object.freeze(ripemd1602);

// node_modules/ethers/lib.esm/crypto/pbkdf2.js
var locked4 = false;
var _pbkdf2 = function(password, salt, iterations, keylen, algo) {
  return (0, import_crypto.pbkdf2Sync)(password, salt, iterations, keylen, algo);
};
var __pbkdf2 = _pbkdf2;
function pbkdf2(_password, _salt, iterations, keylen, algo) {
  const password = getBytes(_password, "password");
  const salt = getBytes(_salt, "salt");
  return hexlify(__pbkdf2(password, salt, iterations, keylen, algo));
}
pbkdf2._ = _pbkdf2;
pbkdf2.lock = function() {
  locked4 = true;
};
pbkdf2.register = function(func) {
  if (locked4) {
    throw new Error("pbkdf2 is locked");
  }
  __pbkdf2 = func;
};
Object.freeze(pbkdf2);

// node_modules/ethers/lib.esm/crypto/random.js
var locked5 = false;
var _randomBytes = function(length) {
  return new Uint8Array((0, import_crypto.randomBytes)(length));
};
var __randomBytes = _randomBytes;
function randomBytes3(length) {
  return __randomBytes(length);
}
randomBytes3._ = _randomBytes;
randomBytes3.lock = function() {
  locked5 = true;
};
randomBytes3.register = function(func) {
  if (locked5) {
    throw new Error("randomBytes is locked");
  }
  __randomBytes = func;
};
Object.freeze(randomBytes3);

// node_modules/ethers/node_modules/@noble/hashes/esm/sha256.js
var Chi = (a, b, c) => a & b ^ ~a & c;
var Maj = (a, b, c) => a & b ^ a & c ^ b & c;
var SHA256_K = /* @__PURE__ */ new Uint32Array([
  1116352408,
  1899447441,
  3049323471,
  3921009573,
  961987163,
  1508970993,
  2453635748,
  2870763221,
  3624381080,
  310598401,
  607225278,
  1426881987,
  1925078388,
  2162078206,
  2614888103,
  3248222580,
  3835390401,
  4022224774,
  264347078,
  604807628,
  770255983,
  1249150122,
  1555081692,
  1996064986,
  2554220882,
  2821834349,
  2952996808,
  3210313671,
  3336571891,
  3584528711,
  113926993,
  338241895,
  666307205,
  773529912,
  1294757372,
  1396182291,
  1695183700,
  1986661051,
  2177026350,
  2456956037,
  2730485921,
  2820302411,
  3259730800,
  3345764771,
  3516065817,
  3600352804,
  4094571909,
  275423344,
  430227734,
  506948616,
  659060556,
  883997877,
  958139571,
  1322822218,
  1537002063,
  1747873779,
  1955562222,
  2024104815,
  2227730452,
  2361852424,
  2428436474,
  2756734187,
  3204031479,
  3329325298
]);
var IV = /* @__PURE__ */ new Uint32Array([
  1779033703,
  3144134277,
  1013904242,
  2773480762,
  1359893119,
  2600822924,
  528734635,
  1541459225
]);
var SHA256_W = /* @__PURE__ */ new Uint32Array(64);
var SHA256 = class extends SHA2 {
  constructor() {
    super(64, 32, 8, false);
    this.A = IV[0] | 0;
    this.B = IV[1] | 0;
    this.C = IV[2] | 0;
    this.D = IV[3] | 0;
    this.E = IV[4] | 0;
    this.F = IV[5] | 0;
    this.G = IV[6] | 0;
    this.H = IV[7] | 0;
  }
  get() {
    const { A, B, C, D, E, F, G, H } = this;
    return [A, B, C, D, E, F, G, H];
  }
  // prettier-ignore
  set(A, B, C, D, E, F, G, H) {
    this.A = A | 0;
    this.B = B | 0;
    this.C = C | 0;
    this.D = D | 0;
    this.E = E | 0;
    this.F = F | 0;
    this.G = G | 0;
    this.H = H | 0;
  }
  process(view, offset) {
    for (let i = 0; i < 16; i++, offset += 4)
      SHA256_W[i] = view.getUint32(offset, false);
    for (let i = 16; i < 64; i++) {
      const W15 = SHA256_W[i - 15];
      const W2 = SHA256_W[i - 2];
      const s0 = rotr(W15, 7) ^ rotr(W15, 18) ^ W15 >>> 3;
      const s1 = rotr(W2, 17) ^ rotr(W2, 19) ^ W2 >>> 10;
      SHA256_W[i] = s1 + SHA256_W[i - 7] + s0 + SHA256_W[i - 16] | 0;
    }
    let { A, B, C, D, E, F, G, H } = this;
    for (let i = 0; i < 64; i++) {
      const sigma1 = rotr(E, 6) ^ rotr(E, 11) ^ rotr(E, 25);
      const T12 = H + sigma1 + Chi(E, F, G) + SHA256_K[i] + SHA256_W[i] | 0;
      const sigma0 = rotr(A, 2) ^ rotr(A, 13) ^ rotr(A, 22);
      const T22 = sigma0 + Maj(A, B, C) | 0;
      H = G;
      G = F;
      F = E;
      E = D + T12 | 0;
      D = C;
      C = B;
      B = A;
      A = T12 + T22 | 0;
    }
    A = A + this.A | 0;
    B = B + this.B | 0;
    C = C + this.C | 0;
    D = D + this.D | 0;
    E = E + this.E | 0;
    F = F + this.F | 0;
    G = G + this.G | 0;
    H = H + this.H | 0;
    this.set(A, B, C, D, E, F, G, H);
  }
  roundClean() {
    SHA256_W.fill(0);
  }
  destroy() {
    this.set(0, 0, 0, 0, 0, 0, 0, 0);
    this.buffer.fill(0);
  }
};
var sha256 = /* @__PURE__ */ wrapConstructor(() => new SHA256());

// node_modules/ethers/node_modules/@noble/hashes/esm/hmac.js
var HMAC = class extends Hash {
  constructor(hash2, _key) {
    super();
    this.finished = false;
    this.destroyed = false;
    hash(hash2);
    const key = toBytes(_key);
    this.iHash = hash2.create();
    if (typeof this.iHash.update !== "function")
      throw new Error("Expected instance of class which extends utils.Hash");
    this.blockLen = this.iHash.blockLen;
    this.outputLen = this.iHash.outputLen;
    const blockLen = this.blockLen;
    const pad = new Uint8Array(blockLen);
    pad.set(key.length > blockLen ? hash2.create().update(key).digest() : key);
    for (let i = 0; i < pad.length; i++)
      pad[i] ^= 54;
    this.iHash.update(pad);
    this.oHash = hash2.create();
    for (let i = 0; i < pad.length; i++)
      pad[i] ^= 54 ^ 92;
    this.oHash.update(pad);
    pad.fill(0);
  }
  update(buf) {
    exists(this);
    this.iHash.update(buf);
    return this;
  }
  digestInto(out) {
    exists(this);
    bytes(out, this.outputLen);
    this.finished = true;
    this.iHash.digestInto(out);
    this.oHash.update(out);
    this.oHash.digestInto(out);
    this.destroy();
  }
  digest() {
    const out = new Uint8Array(this.oHash.outputLen);
    this.digestInto(out);
    return out;
  }
  _cloneInto(to) {
    to || (to = Object.create(Object.getPrototypeOf(this), {}));
    const { oHash, iHash, finished, destroyed, blockLen, outputLen } = this;
    to = to;
    to.finished = finished;
    to.destroyed = destroyed;
    to.blockLen = blockLen;
    to.outputLen = outputLen;
    to.oHash = oHash._cloneInto(to.oHash);
    to.iHash = iHash._cloneInto(to.iHash);
    return to;
  }
  destroy() {
    this.destroyed = true;
    this.oHash.destroy();
    this.iHash.destroy();
  }
};
var hmac = (hash2, key, message) => new HMAC(hash2, key).update(message).digest();
hmac.create = (hash2, key) => new HMAC(hash2, key);

// node_modules/ethers/node_modules/@noble/hashes/esm/pbkdf2.js
function pbkdf2Init(hash2, _password, _salt, _opts) {
  hash(hash2);
  const opts = checkOpts({ dkLen: 32, asyncTick: 10 }, _opts);
  const { c, dkLen, asyncTick } = opts;
  number(c);
  number(dkLen);
  number(asyncTick);
  if (c < 1)
    throw new Error("PBKDF2: iterations (c) should be >= 1");
  const password = toBytes(_password);
  const salt = toBytes(_salt);
  const DK = new Uint8Array(dkLen);
  const PRF = hmac.create(hash2, password);
  const PRFSalt = PRF._cloneInto().update(salt);
  return { c, dkLen, asyncTick, DK, PRF, PRFSalt };
}
function pbkdf2Output(PRF, PRFSalt, DK, prfW, u) {
  PRF.destroy();
  PRFSalt.destroy();
  if (prfW)
    prfW.destroy();
  u.fill(0);
  return DK;
}
function pbkdf22(hash2, password, salt, opts) {
  const { c, dkLen, DK, PRF, PRFSalt } = pbkdf2Init(hash2, password, salt, opts);
  let prfW;
  const arr = new Uint8Array(4);
  const view = createView(arr);
  const u = new Uint8Array(PRF.outputLen);
  for (let ti = 1, pos = 0; pos < dkLen; ti++, pos += PRF.outputLen) {
    const Ti = DK.subarray(pos, pos + PRF.outputLen);
    view.setInt32(0, ti, false);
    (prfW = PRFSalt._cloneInto(prfW)).update(arr).digestInto(u);
    Ti.set(u.subarray(0, Ti.length));
    for (let ui = 1; ui < c; ui++) {
      PRF._cloneInto(prfW).update(u).digestInto(u);
      for (let i = 0; i < Ti.length; i++)
        Ti[i] ^= u[i];
    }
  }
  return pbkdf2Output(PRF, PRFSalt, DK, prfW, u);
}

// node_modules/ethers/node_modules/@noble/hashes/esm/scrypt.js
var rotl2 = (a, b) => a << b | a >>> 32 - b;
function XorAndSalsa(prev, pi, input, ii, out, oi) {
  let y00 = prev[pi++] ^ input[ii++], y01 = prev[pi++] ^ input[ii++];
  let y02 = prev[pi++] ^ input[ii++], y03 = prev[pi++] ^ input[ii++];
  let y04 = prev[pi++] ^ input[ii++], y05 = prev[pi++] ^ input[ii++];
  let y06 = prev[pi++] ^ input[ii++], y07 = prev[pi++] ^ input[ii++];
  let y08 = prev[pi++] ^ input[ii++], y09 = prev[pi++] ^ input[ii++];
  let y10 = prev[pi++] ^ input[ii++], y11 = prev[pi++] ^ input[ii++];
  let y12 = prev[pi++] ^ input[ii++], y13 = prev[pi++] ^ input[ii++];
  let y14 = prev[pi++] ^ input[ii++], y15 = prev[pi++] ^ input[ii++];
  let x00 = y00, x01 = y01, x02 = y02, x03 = y03, x04 = y04, x05 = y05, x06 = y06, x07 = y07, x08 = y08, x09 = y09, x10 = y10, x11 = y11, x12 = y12, x13 = y13, x14 = y14, x15 = y15;
  for (let i = 0; i < 8; i += 2) {
    x04 ^= rotl2(x00 + x12 | 0, 7);
    x08 ^= rotl2(x04 + x00 | 0, 9);
    x12 ^= rotl2(x08 + x04 | 0, 13);
    x00 ^= rotl2(x12 + x08 | 0, 18);
    x09 ^= rotl2(x05 + x01 | 0, 7);
    x13 ^= rotl2(x09 + x05 | 0, 9);
    x01 ^= rotl2(x13 + x09 | 0, 13);
    x05 ^= rotl2(x01 + x13 | 0, 18);
    x14 ^= rotl2(x10 + x06 | 0, 7);
    x02 ^= rotl2(x14 + x10 | 0, 9);
    x06 ^= rotl2(x02 + x14 | 0, 13);
    x10 ^= rotl2(x06 + x02 | 0, 18);
    x03 ^= rotl2(x15 + x11 | 0, 7);
    x07 ^= rotl2(x03 + x15 | 0, 9);
    x11 ^= rotl2(x07 + x03 | 0, 13);
    x15 ^= rotl2(x11 + x07 | 0, 18);
    x01 ^= rotl2(x00 + x03 | 0, 7);
    x02 ^= rotl2(x01 + x00 | 0, 9);
    x03 ^= rotl2(x02 + x01 | 0, 13);
    x00 ^= rotl2(x03 + x02 | 0, 18);
    x06 ^= rotl2(x05 + x04 | 0, 7);
    x07 ^= rotl2(x06 + x05 | 0, 9);
    x04 ^= rotl2(x07 + x06 | 0, 13);
    x05 ^= rotl2(x04 + x07 | 0, 18);
    x11 ^= rotl2(x10 + x09 | 0, 7);
    x08 ^= rotl2(x11 + x10 | 0, 9);
    x09 ^= rotl2(x08 + x11 | 0, 13);
    x10 ^= rotl2(x09 + x08 | 0, 18);
    x12 ^= rotl2(x15 + x14 | 0, 7);
    x13 ^= rotl2(x12 + x15 | 0, 9);
    x14 ^= rotl2(x13 + x12 | 0, 13);
    x15 ^= rotl2(x14 + x13 | 0, 18);
  }
  out[oi++] = y00 + x00 | 0;
  out[oi++] = y01 + x01 | 0;
  out[oi++] = y02 + x02 | 0;
  out[oi++] = y03 + x03 | 0;
  out[oi++] = y04 + x04 | 0;
  out[oi++] = y05 + x05 | 0;
  out[oi++] = y06 + x06 | 0;
  out[oi++] = y07 + x07 | 0;
  out[oi++] = y08 + x08 | 0;
  out[oi++] = y09 + x09 | 0;
  out[oi++] = y10 + x10 | 0;
  out[oi++] = y11 + x11 | 0;
  out[oi++] = y12 + x12 | 0;
  out[oi++] = y13 + x13 | 0;
  out[oi++] = y14 + x14 | 0;
  out[oi++] = y15 + x15 | 0;
}
function BlockMix(input, ii, out, oi, r) {
  let head = oi + 0;
  let tail = oi + 16 * r;
  for (let i = 0; i < 16; i++)
    out[tail + i] = input[ii + (2 * r - 1) * 16 + i];
  for (let i = 0; i < r; i++, head += 16, ii += 16) {
    XorAndSalsa(out, tail, input, ii, out, head);
    if (i > 0)
      tail += 16;
    XorAndSalsa(out, head, input, ii += 16, out, tail);
  }
}
function scryptInit(password, salt, _opts) {
  const opts = checkOpts({
    dkLen: 32,
    asyncTick: 10,
    maxmem: 1024 ** 3 + 1024
  }, _opts);
  const { N: N2, r, p, dkLen, asyncTick, maxmem, onProgress } = opts;
  number(N2);
  number(r);
  number(p);
  number(dkLen);
  number(asyncTick);
  number(maxmem);
  if (onProgress !== void 0 && typeof onProgress !== "function")
    throw new Error("progressCb should be function");
  const blockSize = 128 * r;
  const blockSize32 = blockSize / 4;
  if (N2 <= 1 || (N2 & N2 - 1) !== 0 || N2 >= 2 ** (blockSize / 8) || N2 > 2 ** 32) {
    throw new Error("Scrypt: N must be larger than 1, a power of 2, less than 2^(128 * r / 8) and less than 2^32");
  }
  if (p < 0 || p > (2 ** 32 - 1) * 32 / blockSize) {
    throw new Error("Scrypt: p must be a positive integer less than or equal to ((2^32 - 1) * 32) / (128 * r)");
  }
  if (dkLen < 0 || dkLen > (2 ** 32 - 1) * 32) {
    throw new Error("Scrypt: dkLen should be positive integer less than or equal to (2^32 - 1) * 32");
  }
  const memUsed = blockSize * (N2 + p);
  if (memUsed > maxmem) {
    throw new Error(`Scrypt: parameters too large, ${memUsed} (128 * r * (N + p)) > ${maxmem} (maxmem)`);
  }
  const B = pbkdf22(sha256, password, salt, { c: 1, dkLen: blockSize * p });
  const B32 = u32(B);
  const V = u32(new Uint8Array(blockSize * N2));
  const tmp = u32(new Uint8Array(blockSize));
  let blockMixCb = () => {
  };
  if (onProgress) {
    const totalBlockMix = 2 * N2 * p;
    const callbackPer = Math.max(Math.floor(totalBlockMix / 1e4), 1);
    let blockMixCnt = 0;
    blockMixCb = () => {
      blockMixCnt++;
      if (onProgress && (!(blockMixCnt % callbackPer) || blockMixCnt === totalBlockMix))
        onProgress(blockMixCnt / totalBlockMix);
    };
  }
  return { N: N2, r, p, dkLen, blockSize32, V, B32, B, tmp, blockMixCb, asyncTick };
}
function scryptOutput(password, dkLen, B, V, tmp) {
  const res = pbkdf22(sha256, password, B, { c: 1, dkLen });
  B.fill(0);
  V.fill(0);
  tmp.fill(0);
  return res;
}
function scrypt(password, salt, opts) {
  const { N: N2, r, p, dkLen, blockSize32, V, B32, B, tmp, blockMixCb } = scryptInit(password, salt, opts);
  for (let pi = 0; pi < p; pi++) {
    const Pi2 = blockSize32 * pi;
    for (let i = 0; i < blockSize32; i++)
      V[i] = B32[Pi2 + i];
    for (let i = 0, pos = 0; i < N2 - 1; i++) {
      BlockMix(V, pos, V, pos += blockSize32, r);
      blockMixCb();
    }
    BlockMix(V, (N2 - 1) * blockSize32, B32, Pi2, r);
    blockMixCb();
    for (let i = 0; i < N2; i++) {
      const j = B32[Pi2 + blockSize32 - 16] % N2;
      for (let k = 0; k < blockSize32; k++)
        tmp[k] = B32[Pi2 + k] ^ V[j * blockSize32 + k];
      BlockMix(tmp, 0, B32, Pi2, r);
      blockMixCb();
    }
  }
  return scryptOutput(password, dkLen, B, V, tmp);
}
async function scryptAsync(password, salt, opts) {
  const { N: N2, r, p, dkLen, blockSize32, V, B32, B, tmp, blockMixCb, asyncTick } = scryptInit(password, salt, opts);
  for (let pi = 0; pi < p; pi++) {
    const Pi2 = blockSize32 * pi;
    for (let i = 0; i < blockSize32; i++)
      V[i] = B32[Pi2 + i];
    let pos = 0;
    await asyncLoop(N2 - 1, asyncTick, () => {
      BlockMix(V, pos, V, pos += blockSize32, r);
      blockMixCb();
    });
    BlockMix(V, (N2 - 1) * blockSize32, B32, Pi2, r);
    blockMixCb();
    await asyncLoop(N2, asyncTick, () => {
      const j = B32[Pi2 + blockSize32 - 16] % N2;
      for (let k = 0; k < blockSize32; k++)
        tmp[k] = B32[Pi2 + k] ^ V[j * blockSize32 + k];
      BlockMix(tmp, 0, B32, Pi2, r);
      blockMixCb();
    });
  }
  return scryptOutput(password, dkLen, B, V, tmp);
}

// node_modules/ethers/lib.esm/crypto/scrypt.js
var lockedSync = false;
var lockedAsync = false;
var _scryptAsync = async function(passwd, salt, N2, r, p, dkLen, onProgress) {
  return await scryptAsync(passwd, salt, { N: N2, r, p, dkLen, onProgress });
};
var _scryptSync = function(passwd, salt, N2, r, p, dkLen) {
  return scrypt(passwd, salt, { N: N2, r, p, dkLen });
};
var __scryptAsync = _scryptAsync;
var __scryptSync = _scryptSync;
async function scrypt2(_passwd, _salt, N2, r, p, dkLen, progress) {
  const passwd = getBytes(_passwd, "passwd");
  const salt = getBytes(_salt, "salt");
  return hexlify(await __scryptAsync(passwd, salt, N2, r, p, dkLen, progress));
}
scrypt2._ = _scryptAsync;
scrypt2.lock = function() {
  lockedAsync = true;
};
scrypt2.register = function(func) {
  if (lockedAsync) {
    throw new Error("scrypt is locked");
  }
  __scryptAsync = func;
};
Object.freeze(scrypt2);
function scryptSync(_passwd, _salt, N2, r, p, dkLen) {
  const passwd = getBytes(_passwd, "passwd");
  const salt = getBytes(_salt, "salt");
  return hexlify(__scryptSync(passwd, salt, N2, r, p, dkLen));
}
scryptSync._ = _scryptSync;
scryptSync.lock = function() {
  lockedSync = true;
};
scryptSync.register = function(func) {
  if (lockedSync) {
    throw new Error("scryptSync is locked");
  }
  __scryptSync = func;
};
Object.freeze(scryptSync);

// node_modules/ethers/lib.esm/crypto/sha2.js
var _sha256 = function(data4) {
  return (0, import_crypto.createHash)("sha256").update(data4).digest();
};
var _sha512 = function(data4) {
  return (0, import_crypto.createHash)("sha512").update(data4).digest();
};
var __sha256 = _sha256;
var __sha512 = _sha512;
var locked256 = false;
var locked512 = false;
function sha2562(_data) {
  const data4 = getBytes(_data, "data");
  return hexlify(__sha256(data4));
}
sha2562._ = _sha256;
sha2562.lock = function() {
  locked256 = true;
};
sha2562.register = function(func) {
  if (locked256) {
    throw new Error("sha256 is locked");
  }
  __sha256 = func;
};
Object.freeze(sha2562);
function sha512(_data) {
  const data4 = getBytes(_data, "data");
  return hexlify(__sha512(data4));
}
sha512._ = _sha512;
sha512.lock = function() {
  locked512 = true;
};
sha512.register = function(func) {
  if (locked512) {
    throw new Error("sha512 is locked");
  }
  __sha512 = func;
};
Object.freeze(sha2562);

// node_modules/ethers/node_modules/@noble/curves/esm/abstract/utils.js
var utils_exports = {};
__export(utils_exports, {
  bitGet: () => bitGet,
  bitLen: () => bitLen,
  bitMask: () => bitMask,
  bitSet: () => bitSet,
  bytesToHex: () => bytesToHex,
  bytesToNumberBE: () => bytesToNumberBE,
  bytesToNumberLE: () => bytesToNumberLE,
  concatBytes: () => concatBytes2,
  createHmacDrbg: () => createHmacDrbg,
  ensureBytes: () => ensureBytes,
  equalBytes: () => equalBytes,
  hexToBytes: () => hexToBytes,
  hexToNumber: () => hexToNumber,
  numberToBytesBE: () => numberToBytesBE,
  numberToBytesLE: () => numberToBytesLE,
  numberToHexUnpadded: () => numberToHexUnpadded,
  numberToVarBytesBE: () => numberToVarBytesBE,
  utf8ToBytes: () => utf8ToBytes2,
  validateObject: () => validateObject
});
var _0n2 = BigInt(0);
var _1n2 = BigInt(1);
var _2n2 = BigInt(2);
var u8a2 = (a) => a instanceof Uint8Array;
var hexes = /* @__PURE__ */ Array.from({ length: 256 }, (_, i) => i.toString(16).padStart(2, "0"));
function bytesToHex(bytes2) {
  if (!u8a2(bytes2))
    throw new Error("Uint8Array expected");
  let hex2 = "";
  for (let i = 0; i < bytes2.length; i++) {
    hex2 += hexes[bytes2[i]];
  }
  return hex2;
}
function numberToHexUnpadded(num) {
  const hex2 = num.toString(16);
  return hex2.length & 1 ? `0${hex2}` : hex2;
}
function hexToNumber(hex2) {
  if (typeof hex2 !== "string")
    throw new Error("hex string expected, got " + typeof hex2);
  return BigInt(hex2 === "" ? "0" : `0x${hex2}`);
}
function hexToBytes(hex2) {
  if (typeof hex2 !== "string")
    throw new Error("hex string expected, got " + typeof hex2);
  const len = hex2.length;
  if (len % 2)
    throw new Error("padded hex string expected, got unpadded hex of length " + len);
  const array = new Uint8Array(len / 2);
  for (let i = 0; i < array.length; i++) {
    const j = i * 2;
    const hexByte = hex2.slice(j, j + 2);
    const byte = Number.parseInt(hexByte, 16);
    if (Number.isNaN(byte) || byte < 0)
      throw new Error("Invalid byte sequence");
    array[i] = byte;
  }
  return array;
}
function bytesToNumberBE(bytes2) {
  return hexToNumber(bytesToHex(bytes2));
}
function bytesToNumberLE(bytes2) {
  if (!u8a2(bytes2))
    throw new Error("Uint8Array expected");
  return hexToNumber(bytesToHex(Uint8Array.from(bytes2).reverse()));
}
function numberToBytesBE(n, len) {
  return hexToBytes(n.toString(16).padStart(len * 2, "0"));
}
function numberToBytesLE(n, len) {
  return numberToBytesBE(n, len).reverse();
}
function numberToVarBytesBE(n) {
  return hexToBytes(numberToHexUnpadded(n));
}
function ensureBytes(title, hex2, expectedLength) {
  let res;
  if (typeof hex2 === "string") {
    try {
      res = hexToBytes(hex2);
    } catch (e) {
      throw new Error(`${title} must be valid hex string, got "${hex2}". Cause: ${e}`);
    }
  } else if (u8a2(hex2)) {
    res = Uint8Array.from(hex2);
  } else {
    throw new Error(`${title} must be hex string or Uint8Array`);
  }
  const len = res.length;
  if (typeof expectedLength === "number" && len !== expectedLength)
    throw new Error(`${title} expected ${expectedLength} bytes, got ${len}`);
  return res;
}
function concatBytes2(...arrays) {
  const r = new Uint8Array(arrays.reduce((sum, a) => sum + a.length, 0));
  let pad = 0;
  arrays.forEach((a) => {
    if (!u8a2(a))
      throw new Error("Uint8Array expected");
    r.set(a, pad);
    pad += a.length;
  });
  return r;
}
function equalBytes(b1, b2) {
  if (b1.length !== b2.length)
    return false;
  for (let i = 0; i < b1.length; i++)
    if (b1[i] !== b2[i])
      return false;
  return true;
}
function utf8ToBytes2(str) {
  if (typeof str !== "string")
    throw new Error(`utf8ToBytes expected string, got ${typeof str}`);
  return new Uint8Array(new TextEncoder().encode(str));
}
function bitLen(n) {
  let len;
  for (len = 0; n > _0n2; n >>= _1n2, len += 1)
    ;
  return len;
}
function bitGet(n, pos) {
  return n >> BigInt(pos) & _1n2;
}
var bitSet = (n, pos, value) => {
  return n | (value ? _1n2 : _0n2) << BigInt(pos);
};
var bitMask = (n) => (_2n2 << BigInt(n - 1)) - _1n2;
var u8n = (data4) => new Uint8Array(data4);
var u8fr = (arr) => Uint8Array.from(arr);
function createHmacDrbg(hashLen, qByteLen, hmacFn) {
  if (typeof hashLen !== "number" || hashLen < 2)
    throw new Error("hashLen must be a number");
  if (typeof qByteLen !== "number" || qByteLen < 2)
    throw new Error("qByteLen must be a number");
  if (typeof hmacFn !== "function")
    throw new Error("hmacFn must be a function");
  let v = u8n(hashLen);
  let k = u8n(hashLen);
  let i = 0;
  const reset = () => {
    v.fill(1);
    k.fill(0);
    i = 0;
  };
  const h = (...b) => hmacFn(k, v, ...b);
  const reseed = (seed = u8n()) => {
    k = h(u8fr([0]), seed);
    v = h();
    if (seed.length === 0)
      return;
    k = h(u8fr([1]), seed);
    v = h();
  };
  const gen2 = () => {
    if (i++ >= 1e3)
      throw new Error("drbg: tried 1000 values");
    let len = 0;
    const out = [];
    while (len < qByteLen) {
      v = h();
      const sl = v.slice();
      out.push(sl);
      len += v.length;
    }
    return concatBytes2(...out);
  };
  const genUntil = (seed, pred) => {
    reset();
    reseed(seed);
    let res = void 0;
    while (!(res = pred(gen2())))
      reseed();
    reset();
    return res;
  };
  return genUntil;
}
var validatorFns = {
  bigint: (val) => typeof val === "bigint",
  function: (val) => typeof val === "function",
  boolean: (val) => typeof val === "boolean",
  string: (val) => typeof val === "string",
  stringOrUint8Array: (val) => typeof val === "string" || val instanceof Uint8Array,
  isSafeInteger: (val) => Number.isSafeInteger(val),
  array: (val) => Array.isArray(val),
  field: (val, object) => object.Fp.isValid(val),
  hash: (val) => typeof val === "function" && Number.isSafeInteger(val.outputLen)
};
function validateObject(object, validators, optValidators = {}) {
  const checkField = (fieldName, type, isOptional) => {
    const checkVal = validatorFns[type];
    if (typeof checkVal !== "function")
      throw new Error(`Invalid validator "${type}", expected function`);
    const val = object[fieldName];
    if (isOptional && val === void 0)
      return;
    if (!checkVal(val, object)) {
      throw new Error(`Invalid param ${String(fieldName)}=${val} (${typeof val}), expected ${type}`);
    }
  };
  for (const [fieldName, type] of Object.entries(validators))
    checkField(fieldName, type, false);
  for (const [fieldName, type] of Object.entries(optValidators))
    checkField(fieldName, type, true);
  return object;
}

// node_modules/ethers/node_modules/@noble/curves/esm/abstract/modular.js
var _0n3 = BigInt(0);
var _1n3 = BigInt(1);
var _2n3 = BigInt(2);
var _3n = BigInt(3);
var _4n = BigInt(4);
var _5n = BigInt(5);
var _8n = BigInt(8);
var _9n = BigInt(9);
var _16n = BigInt(16);
function mod(a, b) {
  const result = a % b;
  return result >= _0n3 ? result : b + result;
}
function pow(num, power, modulo) {
  if (modulo <= _0n3 || power < _0n3)
    throw new Error("Expected power/modulo > 0");
  if (modulo === _1n3)
    return _0n3;
  let res = _1n3;
  while (power > _0n3) {
    if (power & _1n3)
      res = res * num % modulo;
    num = num * num % modulo;
    power >>= _1n3;
  }
  return res;
}
function pow2(x, power, modulo) {
  let res = x;
  while (power-- > _0n3) {
    res *= res;
    res %= modulo;
  }
  return res;
}
function invert(number2, modulo) {
  if (number2 === _0n3 || modulo <= _0n3) {
    throw new Error(`invert: expected positive integers, got n=${number2} mod=${modulo}`);
  }
  let a = mod(number2, modulo);
  let b = modulo;
  let x = _0n3, y = _1n3, u = _1n3, v = _0n3;
  while (a !== _0n3) {
    const q = b / a;
    const r = b % a;
    const m = x - u * q;
    const n = y - v * q;
    b = a, a = r, x = u, y = v, u = m, v = n;
  }
  const gcd = b;
  if (gcd !== _1n3)
    throw new Error("invert: does not exist");
  return mod(x, modulo);
}
function tonelliShanks(P) {
  const legendreC = (P - _1n3) / _2n3;
  let Q, S2, Z;
  for (Q = P - _1n3, S2 = 0; Q % _2n3 === _0n3; Q /= _2n3, S2++)
    ;
  for (Z = _2n3; Z < P && pow(Z, legendreC, P) !== P - _1n3; Z++)
    ;
  if (S2 === 1) {
    const p1div4 = (P + _1n3) / _4n;
    return function tonelliFast(Fp2, n) {
      const root = Fp2.pow(n, p1div4);
      if (!Fp2.eql(Fp2.sqr(root), n))
        throw new Error("Cannot find square root");
      return root;
    };
  }
  const Q1div2 = (Q + _1n3) / _2n3;
  return function tonelliSlow(Fp2, n) {
    if (Fp2.pow(n, legendreC) === Fp2.neg(Fp2.ONE))
      throw new Error("Cannot find square root");
    let r = S2;
    let g = Fp2.pow(Fp2.mul(Fp2.ONE, Z), Q);
    let x = Fp2.pow(n, Q1div2);
    let b = Fp2.pow(n, Q);
    while (!Fp2.eql(b, Fp2.ONE)) {
      if (Fp2.eql(b, Fp2.ZERO))
        return Fp2.ZERO;
      let m = 1;
      for (let t2 = Fp2.sqr(b); m < r; m++) {
        if (Fp2.eql(t2, Fp2.ONE))
          break;
        t2 = Fp2.sqr(t2);
      }
      const ge = Fp2.pow(g, _1n3 << BigInt(r - m - 1));
      g = Fp2.sqr(ge);
      x = Fp2.mul(x, ge);
      b = Fp2.mul(b, g);
      r = m;
    }
    return x;
  };
}
function FpSqrt(P) {
  if (P % _4n === _3n) {
    const p1div4 = (P + _1n3) / _4n;
    return function sqrt3mod4(Fp2, n) {
      const root = Fp2.pow(n, p1div4);
      if (!Fp2.eql(Fp2.sqr(root), n))
        throw new Error("Cannot find square root");
      return root;
    };
  }
  if (P % _8n === _5n) {
    const c1 = (P - _5n) / _8n;
    return function sqrt5mod8(Fp2, n) {
      const n2 = Fp2.mul(n, _2n3);
      const v = Fp2.pow(n2, c1);
      const nv = Fp2.mul(n, v);
      const i = Fp2.mul(Fp2.mul(nv, _2n3), v);
      const root = Fp2.mul(nv, Fp2.sub(i, Fp2.ONE));
      if (!Fp2.eql(Fp2.sqr(root), n))
        throw new Error("Cannot find square root");
      return root;
    };
  }
  if (P % _16n === _9n) {
  }
  return tonelliShanks(P);
}
var FIELD_FIELDS = [
  "create",
  "isValid",
  "is0",
  "neg",
  "inv",
  "sqrt",
  "sqr",
  "eql",
  "add",
  "sub",
  "mul",
  "pow",
  "div",
  "addN",
  "subN",
  "mulN",
  "sqrN"
];
function validateField(field) {
  const initial = {
    ORDER: "bigint",
    MASK: "bigint",
    BYTES: "isSafeInteger",
    BITS: "isSafeInteger"
  };
  const opts = FIELD_FIELDS.reduce((map, val) => {
    map[val] = "function";
    return map;
  }, initial);
  return validateObject(field, opts);
}
function FpPow(f2, num, power) {
  if (power < _0n3)
    throw new Error("Expected power > 0");
  if (power === _0n3)
    return f2.ONE;
  if (power === _1n3)
    return num;
  let p = f2.ONE;
  let d = num;
  while (power > _0n3) {
    if (power & _1n3)
      p = f2.mul(p, d);
    d = f2.sqr(d);
    power >>= _1n3;
  }
  return p;
}
function FpInvertBatch(f2, nums) {
  const tmp = new Array(nums.length);
  const lastMultiplied = nums.reduce((acc, num, i) => {
    if (f2.is0(num))
      return acc;
    tmp[i] = acc;
    return f2.mul(acc, num);
  }, f2.ONE);
  const inverted = f2.inv(lastMultiplied);
  nums.reduceRight((acc, num, i) => {
    if (f2.is0(num))
      return acc;
    tmp[i] = f2.mul(acc, tmp[i]);
    return f2.mul(acc, num);
  }, inverted);
  return tmp;
}
function nLength(n, nBitLength) {
  const _nBitLength = nBitLength !== void 0 ? nBitLength : n.toString(2).length;
  const nByteLength = Math.ceil(_nBitLength / 8);
  return { nBitLength: _nBitLength, nByteLength };
}
function Field(ORDER, bitLen2, isLE2 = false, redef = {}) {
  if (ORDER <= _0n3)
    throw new Error(`Expected Field ORDER > 0, got ${ORDER}`);
  const { nBitLength: BITS, nByteLength: BYTES } = nLength(ORDER, bitLen2);
  if (BYTES > 2048)
    throw new Error("Field lengths over 2048 bytes are not supported");
  const sqrtP = FpSqrt(ORDER);
  const f2 = Object.freeze({
    ORDER,
    BITS,
    BYTES,
    MASK: bitMask(BITS),
    ZERO: _0n3,
    ONE: _1n3,
    create: (num) => mod(num, ORDER),
    isValid: (num) => {
      if (typeof num !== "bigint")
        throw new Error(`Invalid field element: expected bigint, got ${typeof num}`);
      return _0n3 <= num && num < ORDER;
    },
    is0: (num) => num === _0n3,
    isOdd: (num) => (num & _1n3) === _1n3,
    neg: (num) => mod(-num, ORDER),
    eql: (lhs, rhs) => lhs === rhs,
    sqr: (num) => mod(num * num, ORDER),
    add: (lhs, rhs) => mod(lhs + rhs, ORDER),
    sub: (lhs, rhs) => mod(lhs - rhs, ORDER),
    mul: (lhs, rhs) => mod(lhs * rhs, ORDER),
    pow: (num, power) => FpPow(f2, num, power),
    div: (lhs, rhs) => mod(lhs * invert(rhs, ORDER), ORDER),
    // Same as above, but doesn't normalize
    sqrN: (num) => num * num,
    addN: (lhs, rhs) => lhs + rhs,
    subN: (lhs, rhs) => lhs - rhs,
    mulN: (lhs, rhs) => lhs * rhs,
    inv: (num) => invert(num, ORDER),
    sqrt: redef.sqrt || ((n) => sqrtP(f2, n)),
    invertBatch: (lst) => FpInvertBatch(f2, lst),
    // TODO: do we really need constant cmov?
    // We don't have const-time bigints anyway, so probably will be not very useful
    cmov: (a, b, c) => c ? b : a,
    toBytes: (num) => isLE2 ? numberToBytesLE(num, BYTES) : numberToBytesBE(num, BYTES),
    fromBytes: (bytes2) => {
      if (bytes2.length !== BYTES)
        throw new Error(`Fp.fromBytes: expected ${BYTES}, got ${bytes2.length}`);
      return isLE2 ? bytesToNumberLE(bytes2) : bytesToNumberBE(bytes2);
    }
  });
  return Object.freeze(f2);
}
function getFieldBytesLength(fieldOrder) {
  if (typeof fieldOrder !== "bigint")
    throw new Error("field order must be bigint");
  const bitLength = fieldOrder.toString(2).length;
  return Math.ceil(bitLength / 8);
}
function getMinHashLength(fieldOrder) {
  const length = getFieldBytesLength(fieldOrder);
  return length + Math.ceil(length / 2);
}
function mapHashToField(key, fieldOrder, isLE2 = false) {
  const len = key.length;
  const fieldLen = getFieldBytesLength(fieldOrder);
  const minLen = getMinHashLength(fieldOrder);
  if (len < 16 || len < minLen || len > 1024)
    throw new Error(`expected ${minLen}-1024 bytes of input, got ${len}`);
  const num = isLE2 ? bytesToNumberBE(key) : bytesToNumberLE(key);
  const reduced = mod(num, fieldOrder - _1n3) + _1n3;
  return isLE2 ? numberToBytesLE(reduced, fieldLen) : numberToBytesBE(reduced, fieldLen);
}

// node_modules/ethers/node_modules/@noble/curves/esm/abstract/curve.js
var _0n4 = BigInt(0);
var _1n4 = BigInt(1);
function wNAF(c, bits) {
  const constTimeNegate = (condition, item) => {
    const neg = item.negate();
    return condition ? neg : item;
  };
  const opts = (W) => {
    const windows = Math.ceil(bits / W) + 1;
    const windowSize = 2 ** (W - 1);
    return { windows, windowSize };
  };
  return {
    constTimeNegate,
    // non-const time multiplication ladder
    unsafeLadder(elm, n) {
      let p = c.ZERO;
      let d = elm;
      while (n > _0n4) {
        if (n & _1n4)
          p = p.add(d);
        d = d.double();
        n >>= _1n4;
      }
      return p;
    },
    /**
     * Creates a wNAF precomputation window. Used for caching.
     * Default window size is set by `utils.precompute()` and is equal to 8.
     * Number of precomputed points depends on the curve size:
     * 2^(𝑊−1) * (Math.ceil(𝑛 / 𝑊) + 1), where:
     * - 𝑊 is the window size
     * - 𝑛 is the bitlength of the curve order.
     * For a 256-bit curve and window size 8, the number of precomputed points is 128 * 33 = 4224.
     * @returns precomputed point tables flattened to a single array
     */
    precomputeWindow(elm, W) {
      const { windows, windowSize } = opts(W);
      const points = [];
      let p = elm;
      let base = p;
      for (let window = 0; window < windows; window++) {
        base = p;
        points.push(base);
        for (let i = 1; i < windowSize; i++) {
          base = base.add(p);
          points.push(base);
        }
        p = base.double();
      }
      return points;
    },
    /**
     * Implements ec multiplication using precomputed tables and w-ary non-adjacent form.
     * @param W window size
     * @param precomputes precomputed tables
     * @param n scalar (we don't check here, but should be less than curve order)
     * @returns real and fake (for const-time) points
     */
    wNAF(W, precomputes, n) {
      const { windows, windowSize } = opts(W);
      let p = c.ZERO;
      let f2 = c.BASE;
      const mask2 = BigInt(2 ** W - 1);
      const maxNumber = 2 ** W;
      const shiftBy = BigInt(W);
      for (let window = 0; window < windows; window++) {
        const offset = window * windowSize;
        let wbits = Number(n & mask2);
        n >>= shiftBy;
        if (wbits > windowSize) {
          wbits -= maxNumber;
          n += _1n4;
        }
        const offset1 = offset;
        const offset2 = offset + Math.abs(wbits) - 1;
        const cond1 = window % 2 !== 0;
        const cond2 = wbits < 0;
        if (wbits === 0) {
          f2 = f2.add(constTimeNegate(cond1, precomputes[offset1]));
        } else {
          p = p.add(constTimeNegate(cond2, precomputes[offset2]));
        }
      }
      return { p, f: f2 };
    },
    wNAFCached(P, precomputesMap, n, transform) {
      const W = P._WINDOW_SIZE || 1;
      let comp = precomputesMap.get(P);
      if (!comp) {
        comp = this.precomputeWindow(P, W);
        if (W !== 1) {
          precomputesMap.set(P, transform(comp));
        }
      }
      return this.wNAF(W, comp, n);
    }
  };
}
function validateBasic(curve) {
  validateField(curve.Fp);
  validateObject(curve, {
    n: "bigint",
    h: "bigint",
    Gx: "field",
    Gy: "field"
  }, {
    nBitLength: "isSafeInteger",
    nByteLength: "isSafeInteger"
  });
  return Object.freeze({
    ...nLength(curve.n, curve.nBitLength),
    ...curve,
    ...{ p: curve.Fp.ORDER }
  });
}

// node_modules/ethers/node_modules/@noble/curves/esm/abstract/weierstrass.js
function validatePointOpts(curve) {
  const opts = validateBasic(curve);
  validateObject(opts, {
    a: "field",
    b: "field"
  }, {
    allowedPrivateKeyLengths: "array",
    wrapPrivateKey: "boolean",
    isTorsionFree: "function",
    clearCofactor: "function",
    allowInfinityPoint: "boolean",
    fromBytes: "function",
    toBytes: "function"
  });
  const { endo, Fp: Fp2, a } = opts;
  if (endo) {
    if (!Fp2.eql(a, Fp2.ZERO)) {
      throw new Error("Endomorphism can only be defined for Koblitz curves that have a=0");
    }
    if (typeof endo !== "object" || typeof endo.beta !== "bigint" || typeof endo.splitScalar !== "function") {
      throw new Error("Expected endomorphism with beta: bigint and splitScalar: function");
    }
  }
  return Object.freeze({ ...opts });
}
var { bytesToNumberBE: b2n, hexToBytes: h2b } = utils_exports;
var DER = {
  // asn.1 DER encoding utils
  Err: class DERErr extends Error {
    constructor(m = "") {
      super(m);
    }
  },
  _parseInt(data4) {
    const { Err: E } = DER;
    if (data4.length < 2 || data4[0] !== 2)
      throw new E("Invalid signature integer tag");
    const len = data4[1];
    const res = data4.subarray(2, len + 2);
    if (!len || res.length !== len)
      throw new E("Invalid signature integer: wrong length");
    if (res[0] & 128)
      throw new E("Invalid signature integer: negative");
    if (res[0] === 0 && !(res[1] & 128))
      throw new E("Invalid signature integer: unnecessary leading zero");
    return { d: b2n(res), l: data4.subarray(len + 2) };
  },
  toSig(hex2) {
    const { Err: E } = DER;
    const data4 = typeof hex2 === "string" ? h2b(hex2) : hex2;
    if (!(data4 instanceof Uint8Array))
      throw new Error("ui8a expected");
    let l = data4.length;
    if (l < 2 || data4[0] != 48)
      throw new E("Invalid signature tag");
    if (data4[1] !== l - 2)
      throw new E("Invalid signature: incorrect length");
    const { d: r, l: sBytes } = DER._parseInt(data4.subarray(2));
    const { d: s, l: rBytesLeft } = DER._parseInt(sBytes);
    if (rBytesLeft.length)
      throw new E("Invalid signature: left bytes after parsing");
    return { r, s };
  },
  hexFromSig(sig) {
    const slice = (s2) => Number.parseInt(s2[0], 16) & 8 ? "00" + s2 : s2;
    const h = (num) => {
      const hex2 = num.toString(16);
      return hex2.length & 1 ? `0${hex2}` : hex2;
    };
    const s = slice(h(sig.s));
    const r = slice(h(sig.r));
    const shl = s.length / 2;
    const rhl = r.length / 2;
    const sl = h(shl);
    const rl = h(rhl);
    return `30${h(rhl + shl + 4)}02${rl}${r}02${sl}${s}`;
  }
};
var _0n5 = BigInt(0);
var _1n5 = BigInt(1);
var _2n4 = BigInt(2);
var _3n2 = BigInt(3);
var _4n2 = BigInt(4);
function weierstrassPoints(opts) {
  const CURVE = validatePointOpts(opts);
  const { Fp: Fp2 } = CURVE;
  const toBytes2 = CURVE.toBytes || ((_c, point, _isCompressed) => {
    const a = point.toAffine();
    return concatBytes2(Uint8Array.from([4]), Fp2.toBytes(a.x), Fp2.toBytes(a.y));
  });
  const fromBytes = CURVE.fromBytes || ((bytes2) => {
    const tail = bytes2.subarray(1);
    const x = Fp2.fromBytes(tail.subarray(0, Fp2.BYTES));
    const y = Fp2.fromBytes(tail.subarray(Fp2.BYTES, 2 * Fp2.BYTES));
    return { x, y };
  });
  function weierstrassEquation(x) {
    const { a, b } = CURVE;
    const x2 = Fp2.sqr(x);
    const x3 = Fp2.mul(x2, x);
    return Fp2.add(Fp2.add(x3, Fp2.mul(x, a)), b);
  }
  if (!Fp2.eql(Fp2.sqr(CURVE.Gy), weierstrassEquation(CURVE.Gx)))
    throw new Error("bad generator point: equation left != right");
  function isWithinCurveOrder(num) {
    return typeof num === "bigint" && _0n5 < num && num < CURVE.n;
  }
  function assertGE(num) {
    if (!isWithinCurveOrder(num))
      throw new Error("Expected valid bigint: 0 < bigint < curve.n");
  }
  function normPrivateKeyToScalar(key) {
    const { allowedPrivateKeyLengths: lengths, nByteLength, wrapPrivateKey, n } = CURVE;
    if (lengths && typeof key !== "bigint") {
      if (key instanceof Uint8Array)
        key = bytesToHex(key);
      if (typeof key !== "string" || !lengths.includes(key.length))
        throw new Error("Invalid key");
      key = key.padStart(nByteLength * 2, "0");
    }
    let num;
    try {
      num = typeof key === "bigint" ? key : bytesToNumberBE(ensureBytes("private key", key, nByteLength));
    } catch (error) {
      throw new Error(`private key must be ${nByteLength} bytes, hex or bigint, not ${typeof key}`);
    }
    if (wrapPrivateKey)
      num = mod(num, n);
    assertGE(num);
    return num;
  }
  const pointPrecomputes = /* @__PURE__ */ new Map();
  function assertPrjPoint(other) {
    if (!(other instanceof Point2))
      throw new Error("ProjectivePoint expected");
  }
  class Point2 {
    constructor(px, py, pz) {
      this.px = px;
      this.py = py;
      this.pz = pz;
      if (px == null || !Fp2.isValid(px))
        throw new Error("x required");
      if (py == null || !Fp2.isValid(py))
        throw new Error("y required");
      if (pz == null || !Fp2.isValid(pz))
        throw new Error("z required");
    }
    // Does not validate if the point is on-curve.
    // Use fromHex instead, or call assertValidity() later.
    static fromAffine(p) {
      const { x, y } = p || {};
      if (!p || !Fp2.isValid(x) || !Fp2.isValid(y))
        throw new Error("invalid affine point");
      if (p instanceof Point2)
        throw new Error("projective point not allowed");
      const is0 = (i) => Fp2.eql(i, Fp2.ZERO);
      if (is0(x) && is0(y))
        return Point2.ZERO;
      return new Point2(x, y, Fp2.ONE);
    }
    get x() {
      return this.toAffine().x;
    }
    get y() {
      return this.toAffine().y;
    }
    /**
     * Takes a bunch of Projective Points but executes only one
     * inversion on all of them. Inversion is very slow operation,
     * so this improves performance massively.
     * Optimization: converts a list of projective points to a list of identical points with Z=1.
     */
    static normalizeZ(points) {
      const toInv = Fp2.invertBatch(points.map((p) => p.pz));
      return points.map((p, i) => p.toAffine(toInv[i])).map(Point2.fromAffine);
    }
    /**
     * Converts hash string or Uint8Array to Point.
     * @param hex short/long ECDSA hex
     */
    static fromHex(hex2) {
      const P = Point2.fromAffine(fromBytes(ensureBytes("pointHex", hex2)));
      P.assertValidity();
      return P;
    }
    // Multiplies generator point by privateKey.
    static fromPrivateKey(privateKey) {
      return Point2.BASE.multiply(normPrivateKeyToScalar(privateKey));
    }
    // "Private method", don't use it directly
    _setWindowSize(windowSize) {
      this._WINDOW_SIZE = windowSize;
      pointPrecomputes.delete(this);
    }
    // A point on curve is valid if it conforms to equation.
    assertValidity() {
      if (this.is0()) {
        if (CURVE.allowInfinityPoint && !Fp2.is0(this.py))
          return;
        throw new Error("bad point: ZERO");
      }
      const { x, y } = this.toAffine();
      if (!Fp2.isValid(x) || !Fp2.isValid(y))
        throw new Error("bad point: x or y not FE");
      const left = Fp2.sqr(y);
      const right = weierstrassEquation(x);
      if (!Fp2.eql(left, right))
        throw new Error("bad point: equation left != right");
      if (!this.isTorsionFree())
        throw new Error("bad point: not in prime-order subgroup");
    }
    hasEvenY() {
      const { y } = this.toAffine();
      if (Fp2.isOdd)
        return !Fp2.isOdd(y);
      throw new Error("Field doesn't support isOdd");
    }
    /**
     * Compare one point to another.
     */
    equals(other) {
      assertPrjPoint(other);
      const { px: X1, py: Y1, pz: Z1 } = this;
      const { px: X2, py: Y2, pz: Z2 } = other;
      const U12 = Fp2.eql(Fp2.mul(X1, Z2), Fp2.mul(X2, Z1));
      const U22 = Fp2.eql(Fp2.mul(Y1, Z2), Fp2.mul(Y2, Z1));
      return U12 && U22;
    }
    /**
     * Flips point to one corresponding to (x, -y) in Affine coordinates.
     */
    negate() {
      return new Point2(this.px, Fp2.neg(this.py), this.pz);
    }
    // Renes-Costello-Batina exception-free doubling formula.
    // There is 30% faster Jacobian formula, but it is not complete.
    // https://eprint.iacr.org/2015/1060, algorithm 3
    // Cost: 8M + 3S + 3*a + 2*b3 + 15add.
    double() {
      const { a, b } = CURVE;
      const b3 = Fp2.mul(b, _3n2);
      const { px: X1, py: Y1, pz: Z1 } = this;
      let X3 = Fp2.ZERO, Y3 = Fp2.ZERO, Z3 = Fp2.ZERO;
      let t0 = Fp2.mul(X1, X1);
      let t1 = Fp2.mul(Y1, Y1);
      let t2 = Fp2.mul(Z1, Z1);
      let t3 = Fp2.mul(X1, Y1);
      t3 = Fp2.add(t3, t3);
      Z3 = Fp2.mul(X1, Z1);
      Z3 = Fp2.add(Z3, Z3);
      X3 = Fp2.mul(a, Z3);
      Y3 = Fp2.mul(b3, t2);
      Y3 = Fp2.add(X3, Y3);
      X3 = Fp2.sub(t1, Y3);
      Y3 = Fp2.add(t1, Y3);
      Y3 = Fp2.mul(X3, Y3);
      X3 = Fp2.mul(t3, X3);
      Z3 = Fp2.mul(b3, Z3);
      t2 = Fp2.mul(a, t2);
      t3 = Fp2.sub(t0, t2);
      t3 = Fp2.mul(a, t3);
      t3 = Fp2.add(t3, Z3);
      Z3 = Fp2.add(t0, t0);
      t0 = Fp2.add(Z3, t0);
      t0 = Fp2.add(t0, t2);
      t0 = Fp2.mul(t0, t3);
      Y3 = Fp2.add(Y3, t0);
      t2 = Fp2.mul(Y1, Z1);
      t2 = Fp2.add(t2, t2);
      t0 = Fp2.mul(t2, t3);
      X3 = Fp2.sub(X3, t0);
      Z3 = Fp2.mul(t2, t1);
      Z3 = Fp2.add(Z3, Z3);
      Z3 = Fp2.add(Z3, Z3);
      return new Point2(X3, Y3, Z3);
    }
    // Renes-Costello-Batina exception-free addition formula.
    // There is 30% faster Jacobian formula, but it is not complete.
    // https://eprint.iacr.org/2015/1060, algorithm 1
    // Cost: 12M + 0S + 3*a + 3*b3 + 23add.
    add(other) {
      assertPrjPoint(other);
      const { px: X1, py: Y1, pz: Z1 } = this;
      const { px: X2, py: Y2, pz: Z2 } = other;
      let X3 = Fp2.ZERO, Y3 = Fp2.ZERO, Z3 = Fp2.ZERO;
      const a = CURVE.a;
      const b3 = Fp2.mul(CURVE.b, _3n2);
      let t0 = Fp2.mul(X1, X2);
      let t1 = Fp2.mul(Y1, Y2);
      let t2 = Fp2.mul(Z1, Z2);
      let t3 = Fp2.add(X1, Y1);
      let t4 = Fp2.add(X2, Y2);
      t3 = Fp2.mul(t3, t4);
      t4 = Fp2.add(t0, t1);
      t3 = Fp2.sub(t3, t4);
      t4 = Fp2.add(X1, Z1);
      let t5 = Fp2.add(X2, Z2);
      t4 = Fp2.mul(t4, t5);
      t5 = Fp2.add(t0, t2);
      t4 = Fp2.sub(t4, t5);
      t5 = Fp2.add(Y1, Z1);
      X3 = Fp2.add(Y2, Z2);
      t5 = Fp2.mul(t5, X3);
      X3 = Fp2.add(t1, t2);
      t5 = Fp2.sub(t5, X3);
      Z3 = Fp2.mul(a, t4);
      X3 = Fp2.mul(b3, t2);
      Z3 = Fp2.add(X3, Z3);
      X3 = Fp2.sub(t1, Z3);
      Z3 = Fp2.add(t1, Z3);
      Y3 = Fp2.mul(X3, Z3);
      t1 = Fp2.add(t0, t0);
      t1 = Fp2.add(t1, t0);
      t2 = Fp2.mul(a, t2);
      t4 = Fp2.mul(b3, t4);
      t1 = Fp2.add(t1, t2);
      t2 = Fp2.sub(t0, t2);
      t2 = Fp2.mul(a, t2);
      t4 = Fp2.add(t4, t2);
      t0 = Fp2.mul(t1, t4);
      Y3 = Fp2.add(Y3, t0);
      t0 = Fp2.mul(t5, t4);
      X3 = Fp2.mul(t3, X3);
      X3 = Fp2.sub(X3, t0);
      t0 = Fp2.mul(t3, t1);
      Z3 = Fp2.mul(t5, Z3);
      Z3 = Fp2.add(Z3, t0);
      return new Point2(X3, Y3, Z3);
    }
    subtract(other) {
      return this.add(other.negate());
    }
    is0() {
      return this.equals(Point2.ZERO);
    }
    wNAF(n) {
      return wnaf.wNAFCached(this, pointPrecomputes, n, (comp) => {
        const toInv = Fp2.invertBatch(comp.map((p) => p.pz));
        return comp.map((p, i) => p.toAffine(toInv[i])).map(Point2.fromAffine);
      });
    }
    /**
     * Non-constant-time multiplication. Uses double-and-add algorithm.
     * It's faster, but should only be used when you don't care about
     * an exposed private key e.g. sig verification, which works over *public* keys.
     */
    multiplyUnsafe(n) {
      const I = Point2.ZERO;
      if (n === _0n5)
        return I;
      assertGE(n);
      if (n === _1n5)
        return this;
      const { endo } = CURVE;
      if (!endo)
        return wnaf.unsafeLadder(this, n);
      let { k1neg, k1, k2neg, k2 } = endo.splitScalar(n);
      let k1p = I;
      let k2p = I;
      let d = this;
      while (k1 > _0n5 || k2 > _0n5) {
        if (k1 & _1n5)
          k1p = k1p.add(d);
        if (k2 & _1n5)
          k2p = k2p.add(d);
        d = d.double();
        k1 >>= _1n5;
        k2 >>= _1n5;
      }
      if (k1neg)
        k1p = k1p.negate();
      if (k2neg)
        k2p = k2p.negate();
      k2p = new Point2(Fp2.mul(k2p.px, endo.beta), k2p.py, k2p.pz);
      return k1p.add(k2p);
    }
    /**
     * Constant time multiplication.
     * Uses wNAF method. Windowed method may be 10% faster,
     * but takes 2x longer to generate and consumes 2x memory.
     * Uses precomputes when available.
     * Uses endomorphism for Koblitz curves.
     * @param scalar by which the point would be multiplied
     * @returns New point
     */
    multiply(scalar) {
      assertGE(scalar);
      let n = scalar;
      let point, fake;
      const { endo } = CURVE;
      if (endo) {
        const { k1neg, k1, k2neg, k2 } = endo.splitScalar(n);
        let { p: k1p, f: f1p } = this.wNAF(k1);
        let { p: k2p, f: f2p } = this.wNAF(k2);
        k1p = wnaf.constTimeNegate(k1neg, k1p);
        k2p = wnaf.constTimeNegate(k2neg, k2p);
        k2p = new Point2(Fp2.mul(k2p.px, endo.beta), k2p.py, k2p.pz);
        point = k1p.add(k2p);
        fake = f1p.add(f2p);
      } else {
        const { p, f: f2 } = this.wNAF(n);
        point = p;
        fake = f2;
      }
      return Point2.normalizeZ([point, fake])[0];
    }
    /**
     * Efficiently calculate `aP + bQ`. Unsafe, can expose private key, if used incorrectly.
     * Not using Strauss-Shamir trick: precomputation tables are faster.
     * The trick could be useful if both P and Q are not G (not in our case).
     * @returns non-zero affine point
     */
    multiplyAndAddUnsafe(Q, a, b) {
      const G = Point2.BASE;
      const mul = (P, a2) => a2 === _0n5 || a2 === _1n5 || !P.equals(G) ? P.multiplyUnsafe(a2) : P.multiply(a2);
      const sum = mul(this, a).add(mul(Q, b));
      return sum.is0() ? void 0 : sum;
    }
    // Converts Projective point to affine (x, y) coordinates.
    // Can accept precomputed Z^-1 - for example, from invertBatch.
    // (x, y, z) ∋ (x=x/z, y=y/z)
    toAffine(iz) {
      const { px: x, py: y, pz: z } = this;
      const is0 = this.is0();
      if (iz == null)
        iz = is0 ? Fp2.ONE : Fp2.inv(z);
      const ax = Fp2.mul(x, iz);
      const ay = Fp2.mul(y, iz);
      const zz = Fp2.mul(z, iz);
      if (is0)
        return { x: Fp2.ZERO, y: Fp2.ZERO };
      if (!Fp2.eql(zz, Fp2.ONE))
        throw new Error("invZ was invalid");
      return { x: ax, y: ay };
    }
    isTorsionFree() {
      const { h: cofactor, isTorsionFree } = CURVE;
      if (cofactor === _1n5)
        return true;
      if (isTorsionFree)
        return isTorsionFree(Point2, this);
      throw new Error("isTorsionFree() has not been declared for the elliptic curve");
    }
    clearCofactor() {
      const { h: cofactor, clearCofactor } = CURVE;
      if (cofactor === _1n5)
        return this;
      if (clearCofactor)
        return clearCofactor(Point2, this);
      return this.multiplyUnsafe(CURVE.h);
    }
    toRawBytes(isCompressed = true) {
      this.assertValidity();
      return toBytes2(Point2, this, isCompressed);
    }
    toHex(isCompressed = true) {
      return bytesToHex(this.toRawBytes(isCompressed));
    }
  }
  Point2.BASE = new Point2(CURVE.Gx, CURVE.Gy, Fp2.ONE);
  Point2.ZERO = new Point2(Fp2.ZERO, Fp2.ONE, Fp2.ZERO);
  const _bits = CURVE.nBitLength;
  const wnaf = wNAF(Point2, CURVE.endo ? Math.ceil(_bits / 2) : _bits);
  return {
    CURVE,
    ProjectivePoint: Point2,
    normPrivateKeyToScalar,
    weierstrassEquation,
    isWithinCurveOrder
  };
}
function validateOpts(curve) {
  const opts = validateBasic(curve);
  validateObject(opts, {
    hash: "hash",
    hmac: "function",
    randomBytes: "function"
  }, {
    bits2int: "function",
    bits2int_modN: "function",
    lowS: "boolean"
  });
  return Object.freeze({ lowS: true, ...opts });
}
function weierstrass(curveDef) {
  const CURVE = validateOpts(curveDef);
  const { Fp: Fp2, n: CURVE_ORDER } = CURVE;
  const compressedLen = Fp2.BYTES + 1;
  const uncompressedLen = 2 * Fp2.BYTES + 1;
  function isValidFieldElement(num) {
    return _0n5 < num && num < Fp2.ORDER;
  }
  function modN(a) {
    return mod(a, CURVE_ORDER);
  }
  function invN(a) {
    return invert(a, CURVE_ORDER);
  }
  const { ProjectivePoint: Point2, normPrivateKeyToScalar, weierstrassEquation, isWithinCurveOrder } = weierstrassPoints({
    ...CURVE,
    toBytes(_c, point, isCompressed) {
      const a = point.toAffine();
      const x = Fp2.toBytes(a.x);
      const cat = concatBytes2;
      if (isCompressed) {
        return cat(Uint8Array.from([point.hasEvenY() ? 2 : 3]), x);
      } else {
        return cat(Uint8Array.from([4]), x, Fp2.toBytes(a.y));
      }
    },
    fromBytes(bytes2) {
      const len = bytes2.length;
      const head = bytes2[0];
      const tail = bytes2.subarray(1);
      if (len === compressedLen && (head === 2 || head === 3)) {
        const x = bytesToNumberBE(tail);
        if (!isValidFieldElement(x))
          throw new Error("Point is not on curve");
        const y2 = weierstrassEquation(x);
        let y = Fp2.sqrt(y2);
        const isYOdd = (y & _1n5) === _1n5;
        const isHeadOdd = (head & 1) === 1;
        if (isHeadOdd !== isYOdd)
          y = Fp2.neg(y);
        return { x, y };
      } else if (len === uncompressedLen && head === 4) {
        const x = Fp2.fromBytes(tail.subarray(0, Fp2.BYTES));
        const y = Fp2.fromBytes(tail.subarray(Fp2.BYTES, 2 * Fp2.BYTES));
        return { x, y };
      } else {
        throw new Error(`Point of length ${len} was invalid. Expected ${compressedLen} compressed bytes or ${uncompressedLen} uncompressed bytes`);
      }
    }
  });
  const numToNByteStr = (num) => bytesToHex(numberToBytesBE(num, CURVE.nByteLength));
  function isBiggerThanHalfOrder(number2) {
    const HALF = CURVE_ORDER >> _1n5;
    return number2 > HALF;
  }
  function normalizeS(s) {
    return isBiggerThanHalfOrder(s) ? modN(-s) : s;
  }
  const slcNum = (b, from, to) => bytesToNumberBE(b.slice(from, to));
  class Signature2 {
    constructor(r, s, recovery) {
      this.r = r;
      this.s = s;
      this.recovery = recovery;
      this.assertValidity();
    }
    // pair (bytes of r, bytes of s)
    static fromCompact(hex2) {
      const l = CURVE.nByteLength;
      hex2 = ensureBytes("compactSignature", hex2, l * 2);
      return new Signature2(slcNum(hex2, 0, l), slcNum(hex2, l, 2 * l));
    }
    // DER encoded ECDSA signature
    // https://bitcoin.stackexchange.com/questions/57644/what-are-the-parts-of-a-bitcoin-transaction-input-script
    static fromDER(hex2) {
      const { r, s } = DER.toSig(ensureBytes("DER", hex2));
      return new Signature2(r, s);
    }
    assertValidity() {
      if (!isWithinCurveOrder(this.r))
        throw new Error("r must be 0 < r < CURVE.n");
      if (!isWithinCurveOrder(this.s))
        throw new Error("s must be 0 < s < CURVE.n");
    }
    addRecoveryBit(recovery) {
      return new Signature2(this.r, this.s, recovery);
    }
    recoverPublicKey(msgHash) {
      const { r, s, recovery: rec } = this;
      const h = bits2int_modN(ensureBytes("msgHash", msgHash));
      if (rec == null || ![0, 1, 2, 3].includes(rec))
        throw new Error("recovery id invalid");
      const radj = rec === 2 || rec === 3 ? r + CURVE.n : r;
      if (radj >= Fp2.ORDER)
        throw new Error("recovery id 2 or 3 invalid");
      const prefix = (rec & 1) === 0 ? "02" : "03";
      const R = Point2.fromHex(prefix + numToNByteStr(radj));
      const ir = invN(radj);
      const u1 = modN(-h * ir);
      const u2 = modN(s * ir);
      const Q = Point2.BASE.multiplyAndAddUnsafe(R, u1, u2);
      if (!Q)
        throw new Error("point at infinify");
      Q.assertValidity();
      return Q;
    }
    // Signatures should be low-s, to prevent malleability.
    hasHighS() {
      return isBiggerThanHalfOrder(this.s);
    }
    normalizeS() {
      return this.hasHighS() ? new Signature2(this.r, modN(-this.s), this.recovery) : this;
    }
    // DER-encoded
    toDERRawBytes() {
      return hexToBytes(this.toDERHex());
    }
    toDERHex() {
      return DER.hexFromSig({ r: this.r, s: this.s });
    }
    // padded bytes of r, then padded bytes of s
    toCompactRawBytes() {
      return hexToBytes(this.toCompactHex());
    }
    toCompactHex() {
      return numToNByteStr(this.r) + numToNByteStr(this.s);
    }
  }
  const utils = {
    isValidPrivateKey(privateKey) {
      try {
        normPrivateKeyToScalar(privateKey);
        return true;
      } catch (error) {
        return false;
      }
    },
    normPrivateKeyToScalar,
    /**
     * Produces cryptographically secure private key from random of size
     * (groupLen + ceil(groupLen / 2)) with modulo bias being negligible.
     */
    randomPrivateKey: () => {
      const length = getMinHashLength(CURVE.n);
      return mapHashToField(CURVE.randomBytes(length), CURVE.n);
    },
    /**
     * Creates precompute table for an arbitrary EC point. Makes point "cached".
     * Allows to massively speed-up `point.multiply(scalar)`.
     * @returns cached point
     * @example
     * const fast = utils.precompute(8, ProjectivePoint.fromHex(someonesPubKey));
     * fast.multiply(privKey); // much faster ECDH now
     */
    precompute(windowSize = 8, point = Point2.BASE) {
      point._setWindowSize(windowSize);
      point.multiply(BigInt(3));
      return point;
    }
  };
  function getPublicKey(privateKey, isCompressed = true) {
    return Point2.fromPrivateKey(privateKey).toRawBytes(isCompressed);
  }
  function isProbPub(item) {
    const arr = item instanceof Uint8Array;
    const str = typeof item === "string";
    const len = (arr || str) && item.length;
    if (arr)
      return len === compressedLen || len === uncompressedLen;
    if (str)
      return len === 2 * compressedLen || len === 2 * uncompressedLen;
    if (item instanceof Point2)
      return true;
    return false;
  }
  function getSharedSecret(privateA, publicB, isCompressed = true) {
    if (isProbPub(privateA))
      throw new Error("first arg must be private key");
    if (!isProbPub(publicB))
      throw new Error("second arg must be public key");
    const b = Point2.fromHex(publicB);
    return b.multiply(normPrivateKeyToScalar(privateA)).toRawBytes(isCompressed);
  }
  const bits2int = CURVE.bits2int || function(bytes2) {
    const num = bytesToNumberBE(bytes2);
    const delta = bytes2.length * 8 - CURVE.nBitLength;
    return delta > 0 ? num >> BigInt(delta) : num;
  };
  const bits2int_modN = CURVE.bits2int_modN || function(bytes2) {
    return modN(bits2int(bytes2));
  };
  const ORDER_MASK = bitMask(CURVE.nBitLength);
  function int2octets(num) {
    if (typeof num !== "bigint")
      throw new Error("bigint expected");
    if (!(_0n5 <= num && num < ORDER_MASK))
      throw new Error(`bigint expected < 2^${CURVE.nBitLength}`);
    return numberToBytesBE(num, CURVE.nByteLength);
  }
  function prepSig(msgHash, privateKey, opts = defaultSigOpts) {
    if (["recovered", "canonical"].some((k) => k in opts))
      throw new Error("sign() legacy options not supported");
    const { hash: hash2, randomBytes: randomBytes4 } = CURVE;
    let { lowS, prehash, extraEntropy: ent } = opts;
    if (lowS == null)
      lowS = true;
    msgHash = ensureBytes("msgHash", msgHash);
    if (prehash)
      msgHash = ensureBytes("prehashed msgHash", hash2(msgHash));
    const h1int = bits2int_modN(msgHash);
    const d = normPrivateKeyToScalar(privateKey);
    const seedArgs = [int2octets(d), int2octets(h1int)];
    if (ent != null) {
      const e = ent === true ? randomBytes4(Fp2.BYTES) : ent;
      seedArgs.push(ensureBytes("extraEntropy", e));
    }
    const seed = concatBytes2(...seedArgs);
    const m = h1int;
    function k2sig(kBytes) {
      const k = bits2int(kBytes);
      if (!isWithinCurveOrder(k))
        return;
      const ik = invN(k);
      const q = Point2.BASE.multiply(k).toAffine();
      const r = modN(q.x);
      if (r === _0n5)
        return;
      const s = modN(ik * modN(m + r * d));
      if (s === _0n5)
        return;
      let recovery = (q.x === r ? 0 : 2) | Number(q.y & _1n5);
      let normS = s;
      if (lowS && isBiggerThanHalfOrder(s)) {
        normS = normalizeS(s);
        recovery ^= 1;
      }
      return new Signature2(r, normS, recovery);
    }
    return { seed, k2sig };
  }
  const defaultSigOpts = { lowS: CURVE.lowS, prehash: false };
  const defaultVerOpts = { lowS: CURVE.lowS, prehash: false };
  function sign(msgHash, privKey, opts = defaultSigOpts) {
    const { seed, k2sig } = prepSig(msgHash, privKey, opts);
    const C = CURVE;
    const drbg = createHmacDrbg(C.hash.outputLen, C.nByteLength, C.hmac);
    return drbg(seed, k2sig);
  }
  Point2.BASE._setWindowSize(8);
  function verify(signature, msgHash, publicKey, opts = defaultVerOpts) {
    const sg = signature;
    msgHash = ensureBytes("msgHash", msgHash);
    publicKey = ensureBytes("publicKey", publicKey);
    if ("strict" in opts)
      throw new Error("options.strict was renamed to lowS");
    const { lowS, prehash } = opts;
    let _sig = void 0;
    let P;
    try {
      if (typeof sg === "string" || sg instanceof Uint8Array) {
        try {
          _sig = Signature2.fromDER(sg);
        } catch (derError) {
          if (!(derError instanceof DER.Err))
            throw derError;
          _sig = Signature2.fromCompact(sg);
        }
      } else if (typeof sg === "object" && typeof sg.r === "bigint" && typeof sg.s === "bigint") {
        const { r: r2, s: s2 } = sg;
        _sig = new Signature2(r2, s2);
      } else {
        throw new Error("PARSE");
      }
      P = Point2.fromHex(publicKey);
    } catch (error) {
      if (error.message === "PARSE")
        throw new Error(`signature must be Signature instance, Uint8Array or hex string`);
      return false;
    }
    if (lowS && _sig.hasHighS())
      return false;
    if (prehash)
      msgHash = CURVE.hash(msgHash);
    const { r, s } = _sig;
    const h = bits2int_modN(msgHash);
    const is = invN(s);
    const u1 = modN(h * is);
    const u2 = modN(r * is);
    const R = Point2.BASE.multiplyAndAddUnsafe(P, u1, u2)?.toAffine();
    if (!R)
      return false;
    const v = modN(R.x);
    return v === r;
  }
  return {
    CURVE,
    getPublicKey,
    getSharedSecret,
    sign,
    verify,
    ProjectivePoint: Point2,
    Signature: Signature2,
    utils
  };
}

// node_modules/ethers/node_modules/@noble/curves/esm/_shortw_utils.js
function getHash(hash2) {
  return {
    hash: hash2,
    hmac: (key, ...msgs) => hmac(hash2, key, concatBytes(...msgs)),
    randomBytes: randomBytes2
  };
}
function createCurve(curveDef, defHash) {
  const create = (hash2) => weierstrass({ ...curveDef, ...getHash(hash2) });
  return Object.freeze({ ...create(defHash), create });
}

// node_modules/ethers/node_modules/@noble/curves/esm/secp256k1.js
var secp256k1P = BigInt("0xfffffffffffffffffffffffffffffffffffffffffffffffffffffffefffffc2f");
var secp256k1N = BigInt("0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141");
var _1n6 = BigInt(1);
var _2n5 = BigInt(2);
var divNearest = (a, b) => (a + b / _2n5) / b;
function sqrtMod(y) {
  const P = secp256k1P;
  const _3n3 = BigInt(3), _6n = BigInt(6), _11n = BigInt(11), _22n = BigInt(22);
  const _23n = BigInt(23), _44n = BigInt(44), _88n = BigInt(88);
  const b2 = y * y * y % P;
  const b3 = b2 * b2 * y % P;
  const b6 = pow2(b3, _3n3, P) * b3 % P;
  const b9 = pow2(b6, _3n3, P) * b3 % P;
  const b11 = pow2(b9, _2n5, P) * b2 % P;
  const b22 = pow2(b11, _11n, P) * b11 % P;
  const b44 = pow2(b22, _22n, P) * b22 % P;
  const b88 = pow2(b44, _44n, P) * b44 % P;
  const b176 = pow2(b88, _88n, P) * b88 % P;
  const b220 = pow2(b176, _44n, P) * b44 % P;
  const b223 = pow2(b220, _3n3, P) * b3 % P;
  const t1 = pow2(b223, _23n, P) * b22 % P;
  const t2 = pow2(t1, _6n, P) * b2 % P;
  const root = pow2(t2, _2n5, P);
  if (!Fp.eql(Fp.sqr(root), y))
    throw new Error("Cannot find square root");
  return root;
}
var Fp = Field(secp256k1P, void 0, void 0, { sqrt: sqrtMod });
var secp256k1 = createCurve({
  a: BigInt(0),
  b: BigInt(7),
  Fp,
  n: secp256k1N,
  // Base point (x, y) aka generator point
  Gx: BigInt("55066263022277343669578718895168534326250603453777594175500187360389116729240"),
  Gy: BigInt("32670510020758816978083085130507043184471273380659243275938904335757337482424"),
  h: BigInt(1),
  lowS: true,
  /**
   * secp256k1 belongs to Koblitz curves: it has efficiently computable endomorphism.
   * Endomorphism uses 2x less RAM, speeds up precomputation by 2x and ECDH / key recovery by 20%.
   * For precomputed wNAF it trades off 1/2 init time & 1/3 ram for 20% perf hit.
   * Explanation: https://gist.github.com/paulmillr/eb670806793e84df628a7c434a873066
   */
  endo: {
    beta: BigInt("0x7ae96a2b657c07106e64479eac3434e99cf0497512f58995c1396c28719501ee"),
    splitScalar: (k) => {
      const n = secp256k1N;
      const a1 = BigInt("0x3086d221a7d46bcde86c90e49284eb15");
      const b1 = -_1n6 * BigInt("0xe4437ed6010e88286f547fa90abfe4c3");
      const a2 = BigInt("0x114ca50f7a8e2f3f657c1108d9d44cfd8");
      const b2 = a1;
      const POW_2_128 = BigInt("0x100000000000000000000000000000000");
      const c1 = divNearest(b2 * k, n);
      const c2 = divNearest(-b1 * k, n);
      let k1 = mod(k - c1 * a1 - c2 * a2, n);
      let k2 = mod(-c1 * b1 - c2 * b2, n);
      const k1neg = k1 > POW_2_128;
      const k2neg = k2 > POW_2_128;
      if (k1neg)
        k1 = n - k1;
      if (k2neg)
        k2 = n - k2;
      if (k1 > POW_2_128 || k2 > POW_2_128) {
        throw new Error("splitScalar: Endomorphism failed, k=" + k);
      }
      return { k1neg, k1, k2neg, k2 };
    }
  }
}, sha256);
var _0n6 = BigInt(0);
var Point = secp256k1.ProjectivePoint;

// node_modules/ethers/lib.esm/constants/addresses.js
var ZeroAddress = "0x0000000000000000000000000000000000000000";

// node_modules/ethers/lib.esm/constants/hashes.js
var ZeroHash = "0x0000000000000000000000000000000000000000000000000000000000000000";

// node_modules/ethers/lib.esm/constants/strings.js
var MessagePrefix = "Ethereum Signed Message:\n";

// node_modules/ethers/lib.esm/crypto/signature.js
var BN_03 = BigInt(0);
var BN_12 = BigInt(1);
var BN_2 = BigInt(2);
var BN_27 = BigInt(27);
var BN_28 = BigInt(28);
var BN_35 = BigInt(35);
var BN_N = BigInt("0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141");
var BN_N_2 = BN_N / BN_2;
var inspect = Symbol.for("nodejs.util.inspect.custom");
var _guard = {};
function toUint256(value) {
  return zeroPadValue(toBeArray(value), 32);
}
var Signature = class _Signature {
  #r;
  #s;
  #v;
  #networkV;
  /**
   *  The ``r`` value for a signature.
   *
   *  This represents the ``x`` coordinate of a "reference" or
   *  challenge point, from which the ``y`` can be computed.
   */
  get r() {
    return this.#r;
  }
  set r(value) {
    assertArgument(dataLength(value) === 32, "invalid r", "value", value);
    this.#r = hexlify(value);
  }
  /**
   *  The ``s`` value for a signature.
   */
  get s() {
    assertArgument(parseInt(this.#s.substring(0, 3)) < 8, "non-canonical s; use ._s", "s", this.#s);
    return this.#s;
  }
  set s(_value) {
    assertArgument(dataLength(_value) === 32, "invalid s", "value", _value);
    this.#s = hexlify(_value);
  }
  /**
   *  Return the s value, unchecked for EIP-2 compliance.
   *
   *  This should generally not be used and is for situations where
   *  a non-canonical S value might be relevant, such as Frontier blocks
   *  that were mined prior to EIP-2 or invalid Authorization List
   *  signatures.
   */
  get _s() {
    return this.#s;
  }
  /**
   *  Returns true if the Signature is valid for [[link-eip-2]] signatures.
   */
  isValid() {
    const s = BigInt(this.#s);
    return s <= BN_N_2;
  }
  /**
   *  The ``v`` value for a signature.
   *
   *  Since a given ``x`` value for ``r`` has two possible values for
   *  its correspondin ``y``, the ``v`` indicates which of the two ``y``
   *  values to use.
   *
   *  It is normalized to the values ``27`` or ``28`` for legacy
   *  purposes.
   */
  get v() {
    return this.#v;
  }
  set v(value) {
    const v = getNumber(value, "value");
    assertArgument(v === 27 || v === 28, "invalid v", "v", value);
    this.#v = v;
  }
  /**
   *  The EIP-155 ``v`` for legacy transactions. For non-legacy
   *  transactions, this value is ``null``.
   */
  get networkV() {
    return this.#networkV;
  }
  /**
   *  The chain ID for EIP-155 legacy transactions. For non-legacy
   *  transactions, this value is ``null``.
   */
  get legacyChainId() {
    const v = this.networkV;
    if (v == null) {
      return null;
    }
    return _Signature.getChainId(v);
  }
  /**
   *  The ``yParity`` for the signature.
   *
   *  See ``v`` for more details on how this value is used.
   */
  get yParity() {
    return this.v === 27 ? 0 : 1;
  }
  /**
   *  The [[link-eip-2098]] compact representation of the ``yParity``
   *  and ``s`` compacted into a single ``bytes32``.
   */
  get yParityAndS() {
    const yParityAndS = getBytes(this.s);
    if (this.yParity) {
      yParityAndS[0] |= 128;
    }
    return hexlify(yParityAndS);
  }
  /**
   *  The [[link-eip-2098]] compact representation.
   */
  get compactSerialized() {
    return concat([this.r, this.yParityAndS]);
  }
  /**
   *  The serialized representation.
   */
  get serialized() {
    return concat([this.r, this.s, this.yParity ? "0x1c" : "0x1b"]);
  }
  /**
   *  @private
   */
  constructor(guard, r, s, v) {
    assertPrivate(guard, _guard, "Signature");
    this.#r = r;
    this.#s = s;
    this.#v = v;
    this.#networkV = null;
  }
  /**
   *  Returns the canonical signature.
   *
   *  This is only necessary when dealing with legacy transaction which
   *  did not enforce canonical S values (i.e. [[link-eip-2]]. Most
   *  developers should never require this.
   */
  getCanonical() {
    if (this.isValid()) {
      return this;
    }
    const s = BN_N - BigInt(this._s);
    const v = 55 - this.v;
    const result = new _Signature(_guard, this.r, toUint256(s), v);
    if (this.networkV) {
      result.#networkV = this.networkV;
    }
    return result;
  }
  /**
   *  Returns a new identical [[Signature]].
   */
  clone() {
    const clone = new _Signature(_guard, this.r, this._s, this.v);
    if (this.networkV) {
      clone.#networkV = this.networkV;
    }
    return clone;
  }
  /**
   *  Returns a representation that is compatible with ``JSON.stringify``.
   */
  toJSON() {
    const networkV = this.networkV;
    return {
      _type: "signature",
      networkV: networkV != null ? networkV.toString() : null,
      r: this.r,
      s: this._s,
      v: this.v
    };
  }
  [inspect]() {
    return this.toString();
  }
  toString() {
    if (this.isValid()) {
      return `Signature { r: ${this.r}, s: ${this._s}, v: ${this.v} }`;
    }
    return `Signature { r: ${this.r}, s: ${this._s}, v: ${this.v}, valid: false }`;
  }
  /**
   *  Compute the chain ID from the ``v`` in a legacy EIP-155 transactions.
   *
   *  @example:
   *    Signature.getChainId(45)
   *    //_result:
   *
   *    Signature.getChainId(46)
   *    //_result:
   */
  static getChainId(v) {
    const bv = getBigInt(v, "v");
    if (bv == BN_27 || bv == BN_28) {
      return BN_03;
    }
    assertArgument(bv >= BN_35, "invalid EIP-155 v", "v", v);
    return (bv - BN_35) / BN_2;
  }
  /**
   *  Compute the ``v`` for a chain ID for a legacy EIP-155 transactions.
   *
   *  Legacy transactions which use [[link-eip-155]] hijack the ``v``
   *  property to include the chain ID.
   *
   *  @example:
   *    Signature.getChainIdV(5, 27)
   *    //_result:
   *
   *    Signature.getChainIdV(5, 28)
   *    //_result:
   *
   */
  static getChainIdV(chainId, v) {
    return getBigInt(chainId) * BN_2 + BigInt(35 + v - 27);
  }
  /**
   *  Compute the normalized legacy transaction ``v`` from a ``yParirty``,
   *  a legacy transaction ``v`` or a legacy [[link-eip-155]] transaction.
   *
   *  @example:
   *    // The values 0 and 1 imply v is actually yParity
   *    Signature.getNormalizedV(0)
   *    //_result:
   *
   *    // Legacy non-EIP-1559 transaction (i.e. 27 or 28)
   *    Signature.getNormalizedV(27)
   *    //_result:
   *
   *    // Legacy EIP-155 transaction (i.e. >= 35)
   *    Signature.getNormalizedV(46)
   *    //_result:
   *
   *    // Invalid values throw
   *    Signature.getNormalizedV(5)
   *    //_error:
   */
  static getNormalizedV(v) {
    const bv = getBigInt(v);
    if (bv === BN_03 || bv === BN_27) {
      return 27;
    }
    if (bv === BN_12 || bv === BN_28) {
      return 28;
    }
    assertArgument(bv >= BN_35, "invalid v", "v", v);
    return bv & BN_12 ? 27 : 28;
  }
  /**
   *  Creates a new [[Signature]].
   *
   *  If no %%sig%% is provided, a new [[Signature]] is created
   *  with default values.
   *
   *  If %%sig%% is a string, it is parsed.
   */
  static from(sig) {
    function assertError(check, message) {
      assertArgument(check, message, "signature", sig);
    }
    ;
    if (sig == null) {
      return new _Signature(_guard, ZeroHash, ZeroHash, 27);
    }
    if (typeof sig === "string") {
      const bytes2 = getBytes(sig, "signature");
      if (bytes2.length === 64) {
        const r2 = hexlify(bytes2.slice(0, 32));
        const s2 = bytes2.slice(32, 64);
        const v2 = s2[0] & 128 ? 28 : 27;
        s2[0] &= 127;
        return new _Signature(_guard, r2, hexlify(s2), v2);
      }
      if (bytes2.length === 65) {
        const r2 = hexlify(bytes2.slice(0, 32));
        const s2 = hexlify(bytes2.slice(32, 64));
        const v2 = _Signature.getNormalizedV(bytes2[64]);
        return new _Signature(_guard, r2, s2, v2);
      }
      assertError(false, "invalid raw signature length");
    }
    if (sig instanceof _Signature) {
      return sig.clone();
    }
    const _r = sig.r;
    assertError(_r != null, "missing r");
    const r = toUint256(_r);
    const s = function(s2, yParityAndS) {
      if (s2 != null) {
        return toUint256(s2);
      }
      if (yParityAndS != null) {
        assertError(isHexString(yParityAndS, 32), "invalid yParityAndS");
        const bytes2 = getBytes(yParityAndS);
        bytes2[0] &= 127;
        return hexlify(bytes2);
      }
      assertError(false, "missing s");
    }(sig.s, sig.yParityAndS);
    const { networkV, v } = function(_v, yParityAndS, yParity) {
      if (_v != null) {
        const v2 = getBigInt(_v);
        return {
          networkV: v2 >= BN_35 ? v2 : void 0,
          v: _Signature.getNormalizedV(v2)
        };
      }
      if (yParityAndS != null) {
        assertError(isHexString(yParityAndS, 32), "invalid yParityAndS");
        return { v: getBytes(yParityAndS)[0] & 128 ? 28 : 27 };
      }
      if (yParity != null) {
        switch (getNumber(yParity, "sig.yParity")) {
          case 0:
            return { v: 27 };
          case 1:
            return { v: 28 };
        }
        assertError(false, "invalid yParity");
      }
      assertError(false, "missing v");
    }(sig.v, sig.yParityAndS, sig.yParity);
    const result = new _Signature(_guard, r, s, v);
    if (networkV) {
      result.#networkV = networkV;
    }
    assertError(sig.yParity == null || getNumber(sig.yParity, "sig.yParity") === result.yParity, "yParity mismatch");
    assertError(sig.yParityAndS == null || sig.yParityAndS === result.yParityAndS, "yParityAndS mismatch");
    return result;
  }
};

// node_modules/ethers/lib.esm/crypto/signing-key.js
var SigningKey = class _SigningKey {
  #privateKey;
  /**
   *  Creates a new **SigningKey** for %%privateKey%%.
   */
  constructor(privateKey) {
    assertArgument(dataLength(privateKey) === 32, "invalid private key", "privateKey", "[REDACTED]");
    this.#privateKey = hexlify(privateKey);
  }
  /**
   *  The private key.
   */
  get privateKey() {
    return this.#privateKey;
  }
  /**
   *  The uncompressed public key.
   *
   * This will always begin with the prefix ``0x04`` and be 132
   * characters long (the ``0x`` prefix and 130 hexadecimal nibbles).
   */
  get publicKey() {
    return _SigningKey.computePublicKey(this.#privateKey);
  }
  /**
   *  The compressed public key.
   *
   *  This will always begin with either the prefix ``0x02`` or ``0x03``
   *  and be 68 characters long (the ``0x`` prefix and 33 hexadecimal
   *  nibbles)
   */
  get compressedPublicKey() {
    return _SigningKey.computePublicKey(this.#privateKey, true);
  }
  /**
   *  Return the signature of the signed %%digest%%.
   */
  sign(digest) {
    assertArgument(dataLength(digest) === 32, "invalid digest length", "digest", digest);
    const sig = secp256k1.sign(getBytesCopy(digest), getBytesCopy(this.#privateKey), {
      lowS: true
    });
    return Signature.from({
      r: toBeHex(sig.r, 32),
      s: toBeHex(sig.s, 32),
      v: sig.recovery ? 28 : 27
    });
  }
  /**
   *  Returns the [[link-wiki-ecdh]] shared secret between this
   *  private key and the %%other%% key.
   *
   *  The %%other%% key may be any type of key, a raw public key,
   *  a compressed/uncompressed pubic key or aprivate key.
   *
   *  Best practice is usually to use a cryptographic hash on the
   *  returned value before using it as a symetric secret.
   *
   *  @example:
   *    sign1 = new SigningKey(id("some-secret-1"))
   *    sign2 = new SigningKey(id("some-secret-2"))
   *
   *    // Notice that privA.computeSharedSecret(pubB)...
   *    sign1.computeSharedSecret(sign2.publicKey)
   *    //_result:
   *
   *    // ...is equal to privB.computeSharedSecret(pubA).
   *    sign2.computeSharedSecret(sign1.publicKey)
   *    //_result:
   */
  computeSharedSecret(other) {
    const pubKey = _SigningKey.computePublicKey(other);
    return hexlify(secp256k1.getSharedSecret(getBytesCopy(this.#privateKey), getBytes(pubKey), false));
  }
  /**
   *  Compute the public key for %%key%%, optionally %%compressed%%.
   *
   *  The %%key%% may be any type of key, a raw public key, a
   *  compressed/uncompressed public key or private key.
   *
   *  @example:
   *    sign = new SigningKey(id("some-secret"));
   *
   *    // Compute the uncompressed public key for a private key
   *    SigningKey.computePublicKey(sign.privateKey)
   *    //_result:
   *
   *    // Compute the compressed public key for a private key
   *    SigningKey.computePublicKey(sign.privateKey, true)
   *    //_result:
   *
   *    // Compute the uncompressed public key
   *    SigningKey.computePublicKey(sign.publicKey, false);
   *    //_result:
   *
   *    // Compute the Compressed a public key
   *    SigningKey.computePublicKey(sign.publicKey, true);
   *    //_result:
   */
  static computePublicKey(key, compressed) {
    let bytes2 = getBytes(key, "key");
    if (bytes2.length === 32) {
      const pubKey = secp256k1.getPublicKey(bytes2, !!compressed);
      return hexlify(pubKey);
    }
    if (bytes2.length === 64) {
      const pub = new Uint8Array(65);
      pub[0] = 4;
      pub.set(bytes2, 1);
      bytes2 = pub;
    }
    const point = secp256k1.ProjectivePoint.fromHex(bytes2);
    return hexlify(point.toRawBytes(compressed));
  }
  /**
   *  Returns the public key for the private key which produced the
   *  %%signature%% for the given %%digest%%.
   *
   *  @example:
   *    key = new SigningKey(id("some-secret"))
   *    digest = id("hello world")
   *    sig = key.sign(digest)
   *
   *    // Notice the signer public key...
   *    key.publicKey
   *    //_result:
   *
   *    // ...is equal to the recovered public key
   *    SigningKey.recoverPublicKey(digest, sig)
   *    //_result:
   *
   */
  static recoverPublicKey(digest, signature) {
    assertArgument(dataLength(digest) === 32, "invalid digest length", "digest", digest);
    const sig = Signature.from(signature);
    let secpSig = secp256k1.Signature.fromCompact(getBytesCopy(concat([sig.r, sig.s])));
    secpSig = secpSig.addRecoveryBit(sig.yParity);
    const pubKey = secpSig.recoverPublicKey(getBytesCopy(digest));
    assertArgument(pubKey != null, "invalid signature for digest", "signature", signature);
    return "0x" + pubKey.toHex(false);
  }
  /**
   *  Returns the point resulting from adding the ellipic curve points
   *  %%p0%% and %%p1%%.
   *
   *  This is not a common function most developers should require, but
   *  can be useful for certain privacy-specific techniques.
   *
   *  For example, it is used by [[HDNodeWallet]] to compute child
   *  addresses from parent public keys and chain codes.
   */
  static addPoints(p0, p1, compressed) {
    const pub0 = secp256k1.ProjectivePoint.fromHex(_SigningKey.computePublicKey(p0).substring(2));
    const pub1 = secp256k1.ProjectivePoint.fromHex(_SigningKey.computePublicKey(p1).substring(2));
    return "0x" + pub0.add(pub1).toHex(!!compressed);
  }
};

// node_modules/ethers/lib.esm/address/address.js
var BN_04 = BigInt(0);
var BN_36 = BigInt(36);
function getChecksumAddress(address) {
  address = address.toLowerCase();
  const chars = address.substring(2).split("");
  const expanded = new Uint8Array(40);
  for (let i = 0; i < 40; i++) {
    expanded[i] = chars[i].charCodeAt(0);
  }
  const hashed = getBytes(keccak256(expanded));
  for (let i = 0; i < 40; i += 2) {
    if (hashed[i >> 1] >> 4 >= 8) {
      chars[i] = chars[i].toUpperCase();
    }
    if ((hashed[i >> 1] & 15) >= 8) {
      chars[i + 1] = chars[i + 1].toUpperCase();
    }
  }
  return "0x" + chars.join("");
}
var ibanLookup = {};
for (let i = 0; i < 10; i++) {
  ibanLookup[String(i)] = String(i);
}
for (let i = 0; i < 26; i++) {
  ibanLookup[String.fromCharCode(65 + i)] = String(10 + i);
}
var safeDigits = 15;
function ibanChecksum(address) {
  address = address.toUpperCase();
  address = address.substring(4) + address.substring(0, 2) + "00";
  let expanded = address.split("").map((c) => {
    return ibanLookup[c];
  }).join("");
  while (expanded.length >= safeDigits) {
    let block = expanded.substring(0, safeDigits);
    expanded = parseInt(block, 10) % 97 + expanded.substring(block.length);
  }
  let checksum7 = String(98 - parseInt(expanded, 10) % 97);
  while (checksum7.length < 2) {
    checksum7 = "0" + checksum7;
  }
  return checksum7;
}
var Base36 = function() {
  ;
  const result = {};
  for (let i = 0; i < 36; i++) {
    const key = "0123456789abcdefghijklmnopqrstuvwxyz"[i];
    result[key] = BigInt(i);
  }
  return result;
}();
function fromBase36(value) {
  value = value.toLowerCase();
  let result = BN_04;
  for (let i = 0; i < value.length; i++) {
    result = result * BN_36 + Base36[value[i]];
  }
  return result;
}
function getAddress(address) {
  assertArgument(typeof address === "string", "invalid address", "address", address);
  if (address.match(/^(0x)?[0-9a-fA-F]{40}$/)) {
    if (!address.startsWith("0x")) {
      address = "0x" + address;
    }
    const result = getChecksumAddress(address);
    assertArgument(!address.match(/([A-F].*[a-f])|([a-f].*[A-F])/) || result === address, "bad address checksum", "address", address);
    return result;
  }
  if (address.match(/^XE[0-9]{2}[0-9A-Za-z]{30,31}$/)) {
    assertArgument(address.substring(2, 4) === ibanChecksum(address), "bad icap checksum", "address", address);
    let result = fromBase36(address.substring(4)).toString(16);
    while (result.length < 40) {
      result = "0" + result;
    }
    return getChecksumAddress("0x" + result);
  }
  assertArgument(false, "invalid address", "address", address);
}

// node_modules/ethers/lib.esm/address/checks.js
function isAddressable(value) {
  return value && typeof value.getAddress === "function";
}
async function checkAddress(target, promise) {
  const result = await promise;
  if (result == null || result === "0x0000000000000000000000000000000000000000") {
    assert(typeof target !== "string", "unconfigured name", "UNCONFIGURED_NAME", { value: target });
    assertArgument(false, "invalid AddressLike value; did not resolve to a value address", "target", target);
  }
  return getAddress(result);
}
function resolveAddress(target, resolver) {
  if (typeof target === "string") {
    if (target.match(/^0x[0-9a-f]{40}$/i)) {
      return getAddress(target);
    }
    assert(resolver != null, "ENS resolution requires a provider", "UNSUPPORTED_OPERATION", { operation: "resolveName" });
    return checkAddress(target, resolver.resolveName(target));
  } else if (isAddressable(target)) {
    return checkAddress(target, target.getAddress());
  } else if (target && typeof target.then === "function") {
    return checkAddress(target, target);
  }
  assertArgument(false, "unsupported addressable value", "target", target);
}

// node_modules/ethers/lib.esm/transaction/accesslist.js
function accessSetify(addr, storageKeys) {
  return {
    address: getAddress(addr),
    storageKeys: storageKeys.map((storageKey, index) => {
      assertArgument(isHexString(storageKey, 32), "invalid slot", `storageKeys[${index}]`, storageKey);
      return storageKey.toLowerCase();
    })
  };
}
function accessListify(value) {
  if (Array.isArray(value)) {
    return value.map((set, index) => {
      if (Array.isArray(set)) {
        assertArgument(set.length === 2, "invalid slot set", `value[${index}]`, set);
        return accessSetify(set[0], set[1]);
      }
      assertArgument(set != null && typeof set === "object", "invalid address-slot set", "value", value);
      return accessSetify(set.address, set.storageKeys);
    });
  }
  assertArgument(value != null && typeof value === "object", "invalid access list", "value", value);
  const result = Object.keys(value).map((addr) => {
    const storageKeys = value[addr].reduce((accum, storageKey) => {
      accum[storageKey] = true;
      return accum;
    }, {});
    return accessSetify(addr, Object.keys(storageKeys).sort());
  });
  result.sort((a, b) => a.address.localeCompare(b.address));
  return result;
}

// node_modules/ethers/lib.esm/transaction/authorization.js
function authorizationify(auth) {
  return {
    address: getAddress(auth.address),
    nonce: getBigInt(auth.nonce != null ? auth.nonce : 0),
    chainId: getBigInt(auth.chainId != null ? auth.chainId : 0),
    signature: Signature.from(auth.signature)
  };
}

// node_modules/ethers/lib.esm/transaction/address.js
function computeAddress(key) {
  let pubkey;
  if (typeof key === "string") {
    pubkey = SigningKey.computePublicKey(key, false);
  } else {
    pubkey = key.publicKey;
  }
  return getAddress(keccak256("0x" + pubkey.substring(4)).substring(26));
}
function recoverAddress(digest, signature) {
  return computeAddress(SigningKey.recoverPublicKey(digest, signature));
}

// node_modules/ethers/lib.esm/transaction/transaction.js
var BN_05 = BigInt(0);
var BN_22 = BigInt(2);
var BN_272 = BigInt(27);
var BN_282 = BigInt(28);
var BN_352 = BigInt(35);
var BN_MAX_UINT = BigInt("0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff");
var inspect2 = Symbol.for("nodejs.util.inspect.custom");
var BLOB_SIZE = 4096 * 32;
var CELL_COUNT = 128;
function getKzgLibrary(kzg) {
  const blobToKzgCommitment = (blob) => {
    if ("computeBlobProof" in kzg) {
      if ("blobToKzgCommitment" in kzg && typeof kzg.blobToKzgCommitment === "function") {
        return getBytes(kzg.blobToKzgCommitment(hexlify(blob)));
      }
    } else if ("blobToKzgCommitment" in kzg && typeof kzg.blobToKzgCommitment === "function") {
      return getBytes(kzg.blobToKzgCommitment(blob));
    }
    if ("blobToKZGCommitment" in kzg && typeof kzg.blobToKZGCommitment === "function") {
      return getBytes(kzg.blobToKZGCommitment(hexlify(blob)));
    }
    assertArgument(false, "unsupported KZG library", "kzg", kzg);
  };
  const computeBlobKzgProof = (blob, commitment) => {
    if ("computeBlobProof" in kzg && typeof kzg.computeBlobProof === "function") {
      return getBytes(kzg.computeBlobProof(hexlify(blob), hexlify(commitment)));
    }
    if ("computeBlobKzgProof" in kzg && typeof kzg.computeBlobKzgProof === "function") {
      return kzg.computeBlobKzgProof(blob, commitment);
    }
    if ("computeBlobKZGProof" in kzg && typeof kzg.computeBlobKZGProof === "function") {
      return getBytes(kzg.computeBlobKZGProof(hexlify(blob), hexlify(commitment)));
    }
    assertArgument(false, "unsupported KZG library", "kzg", kzg);
  };
  return { blobToKzgCommitment, computeBlobKzgProof };
}
function getVersionedHash(version2, hash2) {
  let versioned = version2.toString(16);
  while (versioned.length < 2) {
    versioned = "0" + versioned;
  }
  versioned += sha2562(hash2).substring(4);
  return "0x" + versioned;
}
function handleAddress(value) {
  if (value === "0x") {
    return null;
  }
  return getAddress(value);
}
function handleAccessList(value, param) {
  try {
    return accessListify(value);
  } catch (error) {
    assertArgument(false, error.message, param, value);
  }
}
function handleAuthorizationList(value, param) {
  try {
    if (!Array.isArray(value)) {
      throw new Error("authorizationList: invalid array");
    }
    const result = [];
    for (let i = 0; i < value.length; i++) {
      const auth = value[i];
      if (!Array.isArray(auth)) {
        throw new Error(`authorization[${i}]: invalid array`);
      }
      if (auth.length !== 6) {
        throw new Error(`authorization[${i}]: wrong length`);
      }
      if (!auth[1]) {
        throw new Error(`authorization[${i}]: null address`);
      }
      result.push({
        address: handleAddress(auth[1]),
        nonce: handleUint(auth[2], "nonce"),
        chainId: handleUint(auth[0], "chainId"),
        signature: Signature.from({
          yParity: handleNumber(auth[3], "yParity"),
          r: zeroPadValue(auth[4], 32),
          s: zeroPadValue(auth[5], 32)
        })
      });
    }
    return result;
  } catch (error) {
    assertArgument(false, error.message, param, value);
  }
}
function handleNumber(_value, param) {
  if (_value === "0x") {
    return 0;
  }
  return getNumber(_value, param);
}
function handleUint(_value, param) {
  if (_value === "0x") {
    return BN_05;
  }
  const value = getBigInt(_value, param);
  assertArgument(value <= BN_MAX_UINT, "value exceeds uint size", param, value);
  return value;
}
function formatNumber(_value, name) {
  const value = getBigInt(_value, "value");
  const result = toBeArray(value);
  assertArgument(result.length <= 32, `value too large`, `tx.${name}`, value);
  return result;
}
function formatAccessList(value) {
  return accessListify(value).map((set) => [set.address, set.storageKeys]);
}
function formatAuthorizationList(value) {
  return value.map((a) => {
    return [
      formatNumber(a.chainId, "chainId"),
      a.address,
      formatNumber(a.nonce, "nonce"),
      formatNumber(a.signature.yParity, "yParity"),
      toBeArray(a.signature.r),
      toBeArray(a.signature._s)
    ];
  });
}
function formatHashes(value, param) {
  assertArgument(Array.isArray(value), `invalid ${param}`, "value", value);
  for (let i = 0; i < value.length; i++) {
    assertArgument(isHexString(value[i], 32), "invalid ${ param } hash", `value[${i}]`, value[i]);
  }
  return value;
}
function _parseLegacy(data4) {
  const fields = decodeRlp(data4);
  assertArgument(Array.isArray(fields) && (fields.length === 9 || fields.length === 6), "invalid field count for legacy transaction", "data", data4);
  const tx = {
    type: 0,
    nonce: handleNumber(fields[0], "nonce"),
    gasPrice: handleUint(fields[1], "gasPrice"),
    gasLimit: handleUint(fields[2], "gasLimit"),
    to: handleAddress(fields[3]),
    value: handleUint(fields[4], "value"),
    data: hexlify(fields[5]),
    chainId: BN_05
  };
  if (fields.length === 6) {
    return tx;
  }
  const v = handleUint(fields[6], "v");
  const r = handleUint(fields[7], "r");
  const s = handleUint(fields[8], "s");
  if (r === BN_05 && s === BN_05) {
    tx.chainId = v;
  } else {
    let chainId = (v - BN_352) / BN_22;
    if (chainId < BN_05) {
      chainId = BN_05;
    }
    tx.chainId = chainId;
    assertArgument(chainId !== BN_05 || (v === BN_272 || v === BN_282), "non-canonical legacy v", "v", fields[6]);
    tx.signature = Signature.from({
      r: zeroPadValue(fields[7], 32),
      s: zeroPadValue(fields[8], 32),
      v
    });
  }
  return tx;
}
function _serializeLegacy(tx, sig) {
  const fields = [
    formatNumber(tx.nonce, "nonce"),
    formatNumber(tx.gasPrice || 0, "gasPrice"),
    formatNumber(tx.gasLimit, "gasLimit"),
    tx.to || "0x",
    formatNumber(tx.value, "value"),
    tx.data
  ];
  let chainId = BN_05;
  if (tx.chainId != BN_05) {
    chainId = getBigInt(tx.chainId, "tx.chainId");
    assertArgument(!sig || sig.networkV == null || sig.legacyChainId === chainId, "tx.chainId/sig.v mismatch", "sig", sig);
  } else if (tx.signature) {
    const legacy = tx.signature.legacyChainId;
    if (legacy != null) {
      chainId = legacy;
    }
  }
  if (!sig) {
    if (chainId !== BN_05) {
      fields.push(toBeArray(chainId));
      fields.push("0x");
      fields.push("0x");
    }
    return encodeRlp(fields);
  }
  let v = BigInt(27 + sig.yParity);
  if (chainId !== BN_05) {
    v = Signature.getChainIdV(chainId, sig.v);
  } else if (BigInt(sig.v) !== v) {
    assertArgument(false, "tx.chainId/sig.v mismatch", "sig", sig);
  }
  fields.push(toBeArray(v));
  fields.push(toBeArray(sig.r));
  fields.push(toBeArray(sig._s));
  return encodeRlp(fields);
}
function _parseEipSignature(tx, fields) {
  let yParity;
  try {
    yParity = handleNumber(fields[0], "yParity");
    if (yParity !== 0 && yParity !== 1) {
      throw new Error("bad yParity");
    }
  } catch (error) {
    assertArgument(false, "invalid yParity", "yParity", fields[0]);
  }
  const r = zeroPadValue(fields[1], 32);
  const s = zeroPadValue(fields[2], 32);
  const signature = Signature.from({ r, s, yParity });
  tx.signature = signature;
}
function _parseEip1559(data4) {
  const fields = decodeRlp(getBytes(data4).slice(1));
  assertArgument(Array.isArray(fields) && (fields.length === 9 || fields.length === 12), "invalid field count for transaction type: 2", "data", hexlify(data4));
  const tx = {
    type: 2,
    chainId: handleUint(fields[0], "chainId"),
    nonce: handleNumber(fields[1], "nonce"),
    maxPriorityFeePerGas: handleUint(fields[2], "maxPriorityFeePerGas"),
    maxFeePerGas: handleUint(fields[3], "maxFeePerGas"),
    gasPrice: null,
    gasLimit: handleUint(fields[4], "gasLimit"),
    to: handleAddress(fields[5]),
    value: handleUint(fields[6], "value"),
    data: hexlify(fields[7]),
    accessList: handleAccessList(fields[8], "accessList")
  };
  if (fields.length === 9) {
    return tx;
  }
  _parseEipSignature(tx, fields.slice(9));
  return tx;
}
function _serializeEip1559(tx, sig) {
  const fields = [
    formatNumber(tx.chainId, "chainId"),
    formatNumber(tx.nonce, "nonce"),
    formatNumber(tx.maxPriorityFeePerGas || 0, "maxPriorityFeePerGas"),
    formatNumber(tx.maxFeePerGas || 0, "maxFeePerGas"),
    formatNumber(tx.gasLimit, "gasLimit"),
    tx.to || "0x",
    formatNumber(tx.value, "value"),
    tx.data,
    formatAccessList(tx.accessList || [])
  ];
  if (sig) {
    fields.push(formatNumber(sig.yParity, "yParity"));
    fields.push(toBeArray(sig.r));
    fields.push(toBeArray(sig.s));
  }
  return concat(["0x02", encodeRlp(fields)]);
}
function _parseEip2930(data4) {
  const fields = decodeRlp(getBytes(data4).slice(1));
  assertArgument(Array.isArray(fields) && (fields.length === 8 || fields.length === 11), "invalid field count for transaction type: 1", "data", hexlify(data4));
  const tx = {
    type: 1,
    chainId: handleUint(fields[0], "chainId"),
    nonce: handleNumber(fields[1], "nonce"),
    gasPrice: handleUint(fields[2], "gasPrice"),
    gasLimit: handleUint(fields[3], "gasLimit"),
    to: handleAddress(fields[4]),
    value: handleUint(fields[5], "value"),
    data: hexlify(fields[6]),
    accessList: handleAccessList(fields[7], "accessList")
  };
  if (fields.length === 8) {
    return tx;
  }
  _parseEipSignature(tx, fields.slice(8));
  return tx;
}
function _serializeEip2930(tx, sig) {
  const fields = [
    formatNumber(tx.chainId, "chainId"),
    formatNumber(tx.nonce, "nonce"),
    formatNumber(tx.gasPrice || 0, "gasPrice"),
    formatNumber(tx.gasLimit, "gasLimit"),
    tx.to || "0x",
    formatNumber(tx.value, "value"),
    tx.data,
    formatAccessList(tx.accessList || [])
  ];
  if (sig) {
    fields.push(formatNumber(sig.yParity, "recoveryParam"));
    fields.push(toBeArray(sig.r));
    fields.push(toBeArray(sig.s));
  }
  return concat(["0x01", encodeRlp(fields)]);
}
function _parseEip4844(data4) {
  let fields = decodeRlp(getBytes(data4).slice(1));
  let typeName = "3";
  let blobWrapperVersion = null;
  let blobs = null;
  if (fields.length === 4 && Array.isArray(fields[0])) {
    typeName = "3 (network format)";
    const fBlobs = fields[1], fCommits = fields[2], fProofs = fields[3];
    assertArgument(Array.isArray(fBlobs), "invalid network format: blobs not an array", "fields[1]", fBlobs);
    assertArgument(Array.isArray(fCommits), "invalid network format: commitments not an array", "fields[2]", fCommits);
    assertArgument(Array.isArray(fProofs), "invalid network format: proofs not an array", "fields[3]", fProofs);
    assertArgument(fBlobs.length === fCommits.length, "invalid network format: blobs/commitments length mismatch", "fields", fields);
    assertArgument(fBlobs.length === fProofs.length, "invalid network format: blobs/proofs length mismatch", "fields", fields);
    blobs = [];
    for (let i = 0; i < fields[1].length; i++) {
      blobs.push({
        data: fBlobs[i],
        commitment: fCommits[i],
        proof: fProofs[i]
      });
    }
    fields = fields[0];
  } else if (fields.length === 5 && Array.isArray(fields[0])) {
    typeName = "3 (EIP-7594 network format)";
    blobWrapperVersion = getNumber(fields[1]);
    const fBlobs = fields[2], fCommits = fields[3], fProofs = fields[4];
    assertArgument(blobWrapperVersion === 1, `unsupported EIP-7594 network format version: ${blobWrapperVersion}`, "fields[1]", blobWrapperVersion);
    assertArgument(Array.isArray(fBlobs), "invalid EIP-7594 network format: blobs not an array", "fields[2]", fBlobs);
    assertArgument(Array.isArray(fCommits), "invalid EIP-7594 network format: commitments not an array", "fields[3]", fCommits);
    assertArgument(Array.isArray(fProofs), "invalid EIP-7594 network format: proofs not an array", "fields[4]", fProofs);
    assertArgument(fBlobs.length === fCommits.length, "invalid network format: blobs/commitments length mismatch", "fields", fields);
    assertArgument(fBlobs.length * CELL_COUNT === fProofs.length, "invalid network format: blobs/proofs length mismatch", "fields", fields);
    blobs = [];
    for (let i = 0; i < fBlobs.length; i++) {
      const proof = [];
      for (let j = 0; j < CELL_COUNT; j++) {
        proof.push(fProofs[i * CELL_COUNT + j]);
      }
      blobs.push({
        data: fBlobs[i],
        commitment: fCommits[i],
        proof: concat(proof)
      });
    }
    fields = fields[0];
  }
  assertArgument(Array.isArray(fields) && (fields.length === 11 || fields.length === 14), `invalid field count for transaction type: ${typeName}`, "data", hexlify(data4));
  const tx = {
    type: 3,
    chainId: handleUint(fields[0], "chainId"),
    nonce: handleNumber(fields[1], "nonce"),
    maxPriorityFeePerGas: handleUint(fields[2], "maxPriorityFeePerGas"),
    maxFeePerGas: handleUint(fields[3], "maxFeePerGas"),
    gasPrice: null,
    gasLimit: handleUint(fields[4], "gasLimit"),
    to: handleAddress(fields[5]),
    value: handleUint(fields[6], "value"),
    data: hexlify(fields[7]),
    accessList: handleAccessList(fields[8], "accessList"),
    maxFeePerBlobGas: handleUint(fields[9], "maxFeePerBlobGas"),
    blobVersionedHashes: fields[10],
    blobWrapperVersion
  };
  if (blobs) {
    tx.blobs = blobs;
  }
  assertArgument(tx.to != null, `invalid address for transaction type: ${typeName}`, "data", data4);
  assertArgument(Array.isArray(tx.blobVersionedHashes), "invalid blobVersionedHashes: must be an array", "data", data4);
  for (let i = 0; i < tx.blobVersionedHashes.length; i++) {
    assertArgument(isHexString(tx.blobVersionedHashes[i], 32), `invalid blobVersionedHash at index ${i}: must be length 32`, "data", data4);
  }
  if (fields.length === 11) {
    return tx;
  }
  _parseEipSignature(tx, fields.slice(11));
  return tx;
}
function _serializeEip4844(tx, sig, blobs) {
  const fields = [
    formatNumber(tx.chainId, "chainId"),
    formatNumber(tx.nonce, "nonce"),
    formatNumber(tx.maxPriorityFeePerGas || 0, "maxPriorityFeePerGas"),
    formatNumber(tx.maxFeePerGas || 0, "maxFeePerGas"),
    formatNumber(tx.gasLimit, "gasLimit"),
    tx.to || ZeroAddress,
    formatNumber(tx.value, "value"),
    tx.data,
    formatAccessList(tx.accessList || []),
    formatNumber(tx.maxFeePerBlobGas || 0, "maxFeePerBlobGas"),
    formatHashes(tx.blobVersionedHashes || [], "blobVersionedHashes")
  ];
  if (sig) {
    fields.push(formatNumber(sig.yParity, "yParity"));
    fields.push(toBeArray(sig.r));
    fields.push(toBeArray(sig.s));
    if (blobs) {
      if (tx.blobWrapperVersion != null) {
        const wrapperVersion = toBeArray(tx.blobWrapperVersion);
        const cellProofs = [];
        for (const { proof } of blobs) {
          const p = getBytes(proof);
          const cellSize = p.length / CELL_COUNT;
          for (let i = 0; i < p.length; i += cellSize) {
            cellProofs.push(p.subarray(i, i + cellSize));
          }
        }
        return concat([
          "0x03",
          encodeRlp([
            fields,
            wrapperVersion,
            blobs.map((b) => b.data),
            blobs.map((b) => b.commitment),
            cellProofs
          ])
        ]);
      }
      return concat([
        "0x03",
        encodeRlp([
          fields,
          blobs.map((b) => b.data),
          blobs.map((b) => b.commitment),
          blobs.map((b) => b.proof)
        ])
      ]);
    }
  }
  return concat(["0x03", encodeRlp(fields)]);
}
function _parseEip7702(data4) {
  const fields = decodeRlp(getBytes(data4).slice(1));
  assertArgument(Array.isArray(fields) && (fields.length === 10 || fields.length === 13), "invalid field count for transaction type: 4", "data", hexlify(data4));
  const tx = {
    type: 4,
    chainId: handleUint(fields[0], "chainId"),
    nonce: handleNumber(fields[1], "nonce"),
    maxPriorityFeePerGas: handleUint(fields[2], "maxPriorityFeePerGas"),
    maxFeePerGas: handleUint(fields[3], "maxFeePerGas"),
    gasPrice: null,
    gasLimit: handleUint(fields[4], "gasLimit"),
    to: handleAddress(fields[5]),
    value: handleUint(fields[6], "value"),
    data: hexlify(fields[7]),
    accessList: handleAccessList(fields[8], "accessList"),
    authorizationList: handleAuthorizationList(fields[9], "authorizationList")
  };
  if (fields.length === 10) {
    return tx;
  }
  _parseEipSignature(tx, fields.slice(10));
  return tx;
}
function _serializeEip7702(tx, sig) {
  const fields = [
    formatNumber(tx.chainId, "chainId"),
    formatNumber(tx.nonce, "nonce"),
    formatNumber(tx.maxPriorityFeePerGas || 0, "maxPriorityFeePerGas"),
    formatNumber(tx.maxFeePerGas || 0, "maxFeePerGas"),
    formatNumber(tx.gasLimit, "gasLimit"),
    tx.to || "0x",
    formatNumber(tx.value, "value"),
    tx.data,
    formatAccessList(tx.accessList || []),
    formatAuthorizationList(tx.authorizationList || [])
  ];
  if (sig) {
    fields.push(formatNumber(sig.yParity, "yParity"));
    fields.push(toBeArray(sig.r));
    fields.push(toBeArray(sig.s));
  }
  return concat(["0x04", encodeRlp(fields)]);
}
var Transaction = class _Transaction {
  #type;
  #to;
  #data;
  #nonce;
  #gasLimit;
  #gasPrice;
  #maxPriorityFeePerGas;
  #maxFeePerGas;
  #value;
  #chainId;
  #sig;
  #accessList;
  #maxFeePerBlobGas;
  #blobVersionedHashes;
  #kzg;
  #blobs;
  #auths;
  #blobWrapperVersion;
  /**
   *  The transaction type.
   *
   *  If null, the type will be automatically inferred based on
   *  explicit properties.
   */
  get type() {
    return this.#type;
  }
  set type(value) {
    switch (value) {
      case null:
        this.#type = null;
        break;
      case 0:
      case "legacy":
        this.#type = 0;
        break;
      case 1:
      case "berlin":
      case "eip-2930":
        this.#type = 1;
        break;
      case 2:
      case "london":
      case "eip-1559":
        this.#type = 2;
        break;
      case 3:
      case "cancun":
      case "eip-4844":
        this.#type = 3;
        break;
      case 4:
      case "pectra":
      case "eip-7702":
        this.#type = 4;
        break;
      default:
        assertArgument(false, "unsupported transaction type", "type", value);
    }
  }
  /**
   *  The name of the transaction type.
   */
  get typeName() {
    switch (this.type) {
      case 0:
        return "legacy";
      case 1:
        return "eip-2930";
      case 2:
        return "eip-1559";
      case 3:
        return "eip-4844";
      case 4:
        return "eip-7702";
    }
    return null;
  }
  /**
   *  The ``to`` address for the transaction or ``null`` if the
   *  transaction is an ``init`` transaction.
   */
  get to() {
    const value = this.#to;
    if (value == null && this.type === 3) {
      return ZeroAddress;
    }
    return value;
  }
  set to(value) {
    this.#to = value == null ? null : getAddress(value);
  }
  /**
   *  The transaction nonce.
   */
  get nonce() {
    return this.#nonce;
  }
  set nonce(value) {
    this.#nonce = getNumber(value, "value");
  }
  /**
   *  The gas limit.
   */
  get gasLimit() {
    return this.#gasLimit;
  }
  set gasLimit(value) {
    this.#gasLimit = getBigInt(value);
  }
  /**
   *  The gas price.
   *
   *  On legacy networks this defines the fee that will be paid. On
   *  EIP-1559 networks, this should be ``null``.
   */
  get gasPrice() {
    const value = this.#gasPrice;
    if (value == null && (this.type === 0 || this.type === 1)) {
      return BN_05;
    }
    return value;
  }
  set gasPrice(value) {
    this.#gasPrice = value == null ? null : getBigInt(value, "gasPrice");
  }
  /**
   *  The maximum priority fee per unit of gas to pay. On legacy
   *  networks this should be ``null``.
   */
  get maxPriorityFeePerGas() {
    const value = this.#maxPriorityFeePerGas;
    if (value == null) {
      if (this.type === 2 || this.type === 3) {
        return BN_05;
      }
      return null;
    }
    return value;
  }
  set maxPriorityFeePerGas(value) {
    this.#maxPriorityFeePerGas = value == null ? null : getBigInt(value, "maxPriorityFeePerGas");
  }
  /**
   *  The maximum total fee per unit of gas to pay. On legacy
   *  networks this should be ``null``.
   */
  get maxFeePerGas() {
    const value = this.#maxFeePerGas;
    if (value == null) {
      if (this.type === 2 || this.type === 3) {
        return BN_05;
      }
      return null;
    }
    return value;
  }
  set maxFeePerGas(value) {
    this.#maxFeePerGas = value == null ? null : getBigInt(value, "maxFeePerGas");
  }
  /**
   *  The transaction data. For ``init`` transactions this is the
   *  deployment code.
   */
  get data() {
    return this.#data;
  }
  set data(value) {
    this.#data = hexlify(value);
  }
  /**
   *  The amount of ether (in wei) to send in this transactions.
   */
  get value() {
    return this.#value;
  }
  set value(value) {
    this.#value = getBigInt(value, "value");
  }
  /**
   *  The chain ID this transaction is valid on.
   */
  get chainId() {
    return this.#chainId;
  }
  set chainId(value) {
    this.#chainId = getBigInt(value);
  }
  /**
   *  If signed, the signature for this transaction.
   */
  get signature() {
    return this.#sig || null;
  }
  set signature(value) {
    this.#sig = value == null ? null : Signature.from(value);
  }
  isValid() {
    const sig = this.signature;
    if (sig && !sig.isValid()) {
      return false;
    }
    const auths = this.authorizationList;
    if (auths) {
      for (const auth of auths) {
        if (!auth.signature.isValid()) {
          return false;
        }
      }
    }
    return true;
  }
  /**
   *  The access list.
   *
   *  An access list permits discounted (but pre-paid) access to
   *  bytecode and state variable access within contract execution.
   */
  get accessList() {
    const value = this.#accessList || null;
    if (value == null) {
      if (this.type === 1 || this.type === 2 || this.type === 3) {
        return [];
      }
      return null;
    }
    return value;
  }
  set accessList(value) {
    this.#accessList = value == null ? null : accessListify(value);
  }
  get authorizationList() {
    const value = this.#auths || null;
    if (value == null) {
      if (this.type === 4) {
        return [];
      }
    }
    return value;
  }
  set authorizationList(auths) {
    this.#auths = auths == null ? null : auths.map((a) => authorizationify(a));
  }
  /**
   *  The max fee per blob gas for Cancun transactions.
   */
  get maxFeePerBlobGas() {
    const value = this.#maxFeePerBlobGas;
    if (value == null && this.type === 3) {
      return BN_05;
    }
    return value;
  }
  set maxFeePerBlobGas(value) {
    this.#maxFeePerBlobGas = value == null ? null : getBigInt(value, "maxFeePerBlobGas");
  }
  /**
   *  The BLOb versioned hashes for Cancun transactions.
   */
  get blobVersionedHashes() {
    let value = this.#blobVersionedHashes;
    if (value == null && this.type === 3) {
      return [];
    }
    return value;
  }
  set blobVersionedHashes(value) {
    if (value != null) {
      assertArgument(Array.isArray(value), "blobVersionedHashes must be an Array", "value", value);
      value = value.slice();
      for (let i = 0; i < value.length; i++) {
        assertArgument(isHexString(value[i], 32), "invalid blobVersionedHash", `value[${i}]`, value[i]);
      }
    }
    this.#blobVersionedHashes = value;
  }
  /**
   *  The BLObs for the Transaction, if any.
   *
   *  If ``blobs`` is non-``null``, then the [[seriailized]]
   *  will return the network formatted sidecar, otherwise it
   *  will return the standard [[link-eip-2718]] payload. The
   *  [[unsignedSerialized]] is unaffected regardless.
   *
   *  When setting ``blobs``, either fully valid [[Blob]] objects
   *  may be specified (i.e. correctly padded, with correct
   *  committments and proofs) or a raw [[BytesLike]] may
   *  be provided.
   *
   *  If raw [[BytesLike]] are provided, the [[kzg]] property **must**
   *  be already set. The blob will be correctly padded and the
   *  [[KzgLibrary]] will be used to compute the committment and
   *  proof for the blob.
   *
   *  A BLOb is a sequence of field elements, each of which must
   *  be within the BLS field modulo, so some additional processing
   *  may be required to encode arbitrary data to ensure each 32 byte
   *  field is within the valid range.
   *
   *  Setting this automatically populates [[blobVersionedHashes]],
   *  overwriting any existing values. Setting this to ``null``
   *  does **not** remove the [[blobVersionedHashes]], leaving them
   *  present.
   */
  get blobs() {
    if (this.#blobs == null) {
      return null;
    }
    return this.#blobs.map((b) => Object.assign({}, b));
  }
  set blobs(_blobs) {
    if (_blobs == null) {
      this.#blobs = null;
      return;
    }
    const blobs = [];
    const versionedHashes = [];
    for (let i = 0; i < _blobs.length; i++) {
      const blob = _blobs[i];
      if (isBytesLike(blob)) {
        assert(this.#kzg, "adding a raw blob requires a KZG library", "UNSUPPORTED_OPERATION", {
          operation: "set blobs()"
        });
        let data4 = getBytes(blob);
        assertArgument(data4.length <= BLOB_SIZE, "blob is too large", `blobs[${i}]`, blob);
        if (data4.length !== BLOB_SIZE) {
          const padded = new Uint8Array(BLOB_SIZE);
          padded.set(data4);
          data4 = padded;
        }
        const commit = this.#kzg.blobToKzgCommitment(data4);
        const proof = hexlify(this.#kzg.computeBlobKzgProof(data4, commit));
        blobs.push({
          data: hexlify(data4),
          commitment: hexlify(commit),
          proof
        });
        versionedHashes.push(getVersionedHash(1, commit));
      } else {
        const data4 = hexlify(blob.data);
        const commitment = hexlify(blob.commitment);
        const proof = hexlify(blob.proof);
        blobs.push({ data: data4, commitment, proof });
        versionedHashes.push(getVersionedHash(1, commitment));
      }
    }
    this.#blobs = blobs;
    this.#blobVersionedHashes = versionedHashes;
  }
  get kzg() {
    return this.#kzg;
  }
  set kzg(kzg) {
    if (kzg == null) {
      this.#kzg = null;
    } else {
      this.#kzg = getKzgLibrary(kzg);
    }
  }
  get blobWrapperVersion() {
    return this.#blobWrapperVersion;
  }
  set blobWrapperVersion(value) {
    this.#blobWrapperVersion = value;
  }
  /**
   *  Creates a new Transaction with default values.
   */
  constructor() {
    this.#type = null;
    this.#to = null;
    this.#nonce = 0;
    this.#gasLimit = BN_05;
    this.#gasPrice = null;
    this.#maxPriorityFeePerGas = null;
    this.#maxFeePerGas = null;
    this.#data = "0x";
    this.#value = BN_05;
    this.#chainId = BN_05;
    this.#sig = null;
    this.#accessList = null;
    this.#maxFeePerBlobGas = null;
    this.#blobVersionedHashes = null;
    this.#kzg = null;
    this.#blobs = null;
    this.#auths = null;
    this.#blobWrapperVersion = null;
  }
  /**
   *  The transaction hash, if signed. Otherwise, ``null``.
   */
  get hash() {
    if (this.signature == null) {
      return null;
    }
    return keccak256(this.#getSerialized(true, false));
  }
  /**
   *  The pre-image hash of this transaction.
   *
   *  This is the digest that a [[Signer]] must sign to authorize
   *  this transaction.
   */
  get unsignedHash() {
    return keccak256(this.unsignedSerialized);
  }
  /**
   *  The sending address, if signed. Otherwise, ``null``.
   */
  get from() {
    if (this.signature == null) {
      return null;
    }
    return recoverAddress(this.unsignedHash, this.signature.getCanonical());
  }
  /**
   *  The public key of the sender, if signed. Otherwise, ``null``.
   */
  get fromPublicKey() {
    if (this.signature == null) {
      return null;
    }
    return SigningKey.recoverPublicKey(this.unsignedHash, this.signature.getCanonical());
  }
  /**
   *  Returns true if signed.
   *
   *  This provides a Type Guard that properties requiring a signed
   *  transaction are non-null.
   */
  isSigned() {
    return this.signature != null;
  }
  #getSerialized(signed, sidecar) {
    assert(!signed || this.signature != null, "cannot serialize unsigned transaction; maybe you meant .unsignedSerialized", "UNSUPPORTED_OPERATION", { operation: ".serialized" });
    const sig = signed ? this.signature : null;
    switch (this.inferType()) {
      case 0:
        return _serializeLegacy(this, sig);
      case 1:
        return _serializeEip2930(this, sig);
      case 2:
        return _serializeEip1559(this, sig);
      case 3:
        return _serializeEip4844(this, sig, sidecar ? this.blobs : null);
      case 4:
        return _serializeEip7702(this, sig);
    }
    assert(false, "unsupported transaction type", "UNSUPPORTED_OPERATION", { operation: ".serialized" });
  }
  /**
   *  The serialized transaction.
   *
   *  This throws if the transaction is unsigned. For the pre-image,
   *  use [[unsignedSerialized]].
   */
  get serialized() {
    return this.#getSerialized(true, true);
  }
  /**
   *  The transaction pre-image.
   *
   *  The hash of this is the digest which needs to be signed to
   *  authorize this transaction.
   */
  get unsignedSerialized() {
    return this.#getSerialized(false, false);
  }
  /**
   *  Return the most "likely" type; currently the highest
   *  supported transaction type.
   */
  inferType() {
    const types = this.inferTypes();
    if (types.indexOf(2) >= 0) {
      return 2;
    }
    return types.pop();
  }
  /**
   *  Validates the explicit properties and returns a list of compatible
   *  transaction types.
   */
  inferTypes() {
    const hasGasPrice = this.gasPrice != null;
    const hasFee = this.maxFeePerGas != null || this.maxPriorityFeePerGas != null;
    const hasAccessList = this.accessList != null;
    const hasBlob = this.#maxFeePerBlobGas != null || this.#blobVersionedHashes;
    if (this.maxFeePerGas != null && this.maxPriorityFeePerGas != null) {
      assert(this.maxFeePerGas >= this.maxPriorityFeePerGas, "priorityFee cannot be more than maxFee", "BAD_DATA", { value: this });
    }
    assert(!hasFee || this.type !== 0 && this.type !== 1, "transaction type cannot have maxFeePerGas or maxPriorityFeePerGas", "BAD_DATA", { value: this });
    assert(this.type !== 0 || !hasAccessList, "legacy transaction cannot have accessList", "BAD_DATA", { value: this });
    const types = [];
    if (this.type != null) {
      types.push(this.type);
    } else {
      if (this.authorizationList && this.authorizationList.length) {
        types.push(4);
      } else if (hasFee) {
        types.push(2);
      } else if (hasGasPrice) {
        types.push(1);
        if (!hasAccessList) {
          types.push(0);
        }
      } else if (hasAccessList) {
        types.push(1);
        types.push(2);
      } else if (hasBlob && this.to) {
        types.push(3);
      } else {
        types.push(0);
        types.push(1);
        types.push(2);
        types.push(3);
      }
    }
    types.sort();
    return types;
  }
  /**
   *  Returns true if this transaction is a legacy transaction (i.e.
   *  ``type === 0``).
   *
   *  This provides a Type Guard that the related properties are
   *  non-null.
   */
  isLegacy() {
    return this.type === 0;
  }
  /**
   *  Returns true if this transaction is berlin hardform transaction (i.e.
   *  ``type === 1``).
   *
   *  This provides a Type Guard that the related properties are
   *  non-null.
   */
  isBerlin() {
    return this.type === 1;
  }
  /**
   *  Returns true if this transaction is london hardform transaction (i.e.
   *  ``type === 2``).
   *
   *  This provides a Type Guard that the related properties are
   *  non-null.
   */
  isLondon() {
    return this.type === 2;
  }
  /**
   *  Returns true if this transaction is an [[link-eip-4844]] BLOB
   *  transaction.
   *
   *  This provides a Type Guard that the related properties are
   *  non-null.
   */
  isCancun() {
    return this.type === 3;
  }
  /**
   *  Create a copy of this transaciton.
   */
  clone() {
    return _Transaction.from(this);
  }
  /**
   *  Return a JSON-friendly object.
   */
  toJSON() {
    const s = (v) => {
      if (v == null) {
        return null;
      }
      return v.toString();
    };
    return {
      type: this.type,
      to: this.to,
      //            from: this.from,
      data: this.data,
      nonce: this.nonce,
      gasLimit: s(this.gasLimit),
      gasPrice: s(this.gasPrice),
      maxPriorityFeePerGas: s(this.maxPriorityFeePerGas),
      maxFeePerGas: s(this.maxFeePerGas),
      value: s(this.value),
      chainId: s(this.chainId),
      sig: this.signature ? this.signature.toJSON() : null,
      accessList: this.accessList
    };
  }
  [inspect2]() {
    return this.toString();
  }
  toString() {
    const output2 = [];
    const add = (key) => {
      let value = this[key];
      if (typeof value === "string") {
        value = JSON.stringify(value);
      }
      output2.push(`${key}: ${value}`);
    };
    if (this.type) {
      add("type");
    }
    add("to");
    add("data");
    add("nonce");
    add("gasLimit");
    add("value");
    if (this.chainId != null) {
      add("chainId");
    }
    if (this.signature) {
      add("from");
      output2.push(`signature: ${this.signature.toString()}`);
    }
    const auths = this.authorizationList;
    if (auths) {
      const outputAuths = [];
      for (const auth of auths) {
        const o = [];
        o.push(`address: ${JSON.stringify(auth.address)}`);
        if (auth.nonce != null) {
          o.push(`nonce: ${auth.nonce}`);
        }
        if (auth.chainId != null) {
          o.push(`chainId: ${auth.chainId}`);
        }
        if (auth.signature) {
          o.push(`signature: ${auth.signature.toString()}`);
        }
        outputAuths.push(`Authorization { ${o.join(", ")} }`);
      }
      output2.push(`authorizations: [ ${outputAuths.join(", ")} ]`);
    }
    return `Transaction { ${output2.join(", ")} }`;
  }
  /**
   *  Create a **Transaction** from a serialized transaction or a
   *  Transaction-like object.
   */
  static from(tx) {
    if (tx == null) {
      return new _Transaction();
    }
    if (typeof tx === "string") {
      const payload = getBytes(tx);
      if (payload[0] >= 127) {
        return _Transaction.from(_parseLegacy(payload));
      }
      switch (payload[0]) {
        case 1:
          return _Transaction.from(_parseEip2930(payload));
        case 2:
          return _Transaction.from(_parseEip1559(payload));
        case 3:
          return _Transaction.from(_parseEip4844(payload));
        case 4:
          return _Transaction.from(_parseEip7702(payload));
      }
      assert(false, "unsupported transaction type", "UNSUPPORTED_OPERATION", { operation: "from" });
    }
    const result = new _Transaction();
    if (tx.type != null) {
      result.type = tx.type;
    }
    if (tx.to != null) {
      result.to = tx.to;
    }
    if (tx.nonce != null) {
      result.nonce = tx.nonce;
    }
    if (tx.gasLimit != null) {
      result.gasLimit = tx.gasLimit;
    }
    if (tx.gasPrice != null) {
      result.gasPrice = tx.gasPrice;
    }
    if (tx.maxPriorityFeePerGas != null) {
      result.maxPriorityFeePerGas = tx.maxPriorityFeePerGas;
    }
    if (tx.maxFeePerGas != null) {
      result.maxFeePerGas = tx.maxFeePerGas;
    }
    if (tx.maxFeePerBlobGas != null) {
      result.maxFeePerBlobGas = tx.maxFeePerBlobGas;
    }
    if (tx.data != null) {
      result.data = tx.data;
    }
    if (tx.value != null) {
      result.value = tx.value;
    }
    if (tx.chainId != null) {
      result.chainId = tx.chainId;
    }
    if (tx.signature != null) {
      result.signature = Signature.from(tx.signature);
    }
    if (tx.accessList != null) {
      result.accessList = tx.accessList;
    }
    if (tx.authorizationList != null) {
      result.authorizationList = tx.authorizationList;
    }
    if (tx.blobVersionedHashes != null) {
      result.blobVersionedHashes = tx.blobVersionedHashes;
    }
    if (tx.kzg != null) {
      result.kzg = tx.kzg;
    }
    if (tx.blobWrapperVersion != null) {
      result.blobWrapperVersion = tx.blobWrapperVersion;
    }
    if (tx.blobs != null) {
      result.blobs = tx.blobs;
    }
    if (tx.hash != null) {
      assertArgument(result.isSigned(), "unsigned transaction cannot define '.hash'", "tx", tx);
      assertArgument(result.hash === tx.hash, "hash mismatch", "tx", tx);
    }
    if (tx.from != null) {
      assertArgument(result.isSigned(), "unsigned transaction cannot define '.from'", "tx", tx);
      assertArgument(result.from.toLowerCase() === (tx.from || "").toLowerCase(), "from mismatch", "tx", tx);
    }
    return result;
  }
};

// node_modules/ethers/lib.esm/hash/authorization.js
function hashAuthorization(auth) {
  assertArgument(typeof auth.address === "string", "invalid address for hashAuthorization", "auth.address", auth);
  return keccak256(concat([
    "0x05",
    encodeRlp([
      auth.chainId != null ? toBeArray(auth.chainId) : "0x",
      getAddress(auth.address),
      auth.nonce != null ? toBeArray(auth.nonce) : "0x"
    ])
  ]));
}

// node_modules/ethers/lib.esm/hash/id.js
function id(value) {
  return keccak256(toUtf8Bytes(value));
}

// node_modules/ethers/lib.esm/hash/message.js
function hashMessage(message) {
  if (typeof message === "string") {
    message = toUtf8Bytes(message);
  }
  return keccak256(concat([
    toUtf8Bytes(MessagePrefix),
    toUtf8Bytes(String(message.length)),
    message
  ]));
}

// node_modules/ethers/lib.esm/hash/typed-data.js
var padding = new Uint8Array(32);
padding.fill(0);
var BN__1 = BigInt(-1);
var BN_06 = BigInt(0);
var BN_13 = BigInt(1);
var BN_MAX_UINT256 = BigInt("0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff");
function hexPadRight(value) {
  const bytes2 = getBytes(value);
  const padOffset = bytes2.length % 32;
  if (padOffset) {
    return concat([bytes2, padding.slice(padOffset)]);
  }
  return hexlify(bytes2);
}
var hexTrue = toBeHex(BN_13, 32);
var hexFalse = toBeHex(BN_06, 32);
var domainFieldTypes = {
  name: "string",
  version: "string",
  chainId: "uint256",
  verifyingContract: "address",
  salt: "bytes32"
};
var domainFieldNames = [
  "name",
  "version",
  "chainId",
  "verifyingContract",
  "salt"
];
function checkString(key) {
  return function(value) {
    assertArgument(typeof value === "string", `invalid domain value for ${JSON.stringify(key)}`, `domain.${key}`, value);
    return value;
  };
}
var domainChecks = {
  name: checkString("name"),
  version: checkString("version"),
  chainId: function(_value) {
    const value = getBigInt(_value, "domain.chainId");
    assertArgument(value >= 0, "invalid chain ID", "domain.chainId", _value);
    if (Number.isSafeInteger(value)) {
      return Number(value);
    }
    return toQuantity(value);
  },
  verifyingContract: function(value) {
    try {
      return getAddress(value).toLowerCase();
    } catch (error) {
    }
    assertArgument(false, `invalid domain value "verifyingContract"`, "domain.verifyingContract", value);
  },
  salt: function(value) {
    const bytes2 = getBytes(value, "domain.salt");
    assertArgument(bytes2.length === 32, `invalid domain value "salt"`, "domain.salt", value);
    return hexlify(bytes2);
  }
};
function getBaseEncoder(type) {
  {
    const match = type.match(/^(u?)int(\d+)$/);
    if (match) {
      const signed = match[1] === "";
      const width = parseInt(match[2]);
      assertArgument(width % 8 === 0 && width !== 0 && width <= 256 && match[2] === String(width), "invalid numeric width", "type", type);
      const boundsUpper = mask(BN_MAX_UINT256, signed ? width - 1 : width);
      const boundsLower = signed ? (boundsUpper + BN_13) * BN__1 : BN_06;
      return function(_value) {
        const value = getBigInt(_value, "value");
        assertArgument(value >= boundsLower && value <= boundsUpper, `value out-of-bounds for ${type}`, "value", value);
        return toBeHex(signed ? toTwos(value, 256) : value, 32);
      };
    }
  }
  {
    const match = type.match(/^bytes(\d+)$/);
    if (match) {
      const width = parseInt(match[1]);
      assertArgument(width !== 0 && width <= 32 && match[1] === String(width), "invalid bytes width", "type", type);
      return function(value) {
        const bytes2 = getBytes(value);
        assertArgument(bytes2.length === width, `invalid length for ${type}`, "value", value);
        return hexPadRight(value);
      };
    }
  }
  switch (type) {
    case "address":
      return function(value) {
        return zeroPadValue(getAddress(value), 32);
      };
    case "bool":
      return function(value) {
        return !value ? hexFalse : hexTrue;
      };
    case "bytes":
      return function(value) {
        return keccak256(value);
      };
    case "string":
      return function(value) {
        return id(value);
      };
  }
  return null;
}
function encodeType(name, fields) {
  return `${name}(${fields.map(({ name: name2, type }) => type + " " + name2).join(",")})`;
}
function splitArray(type) {
  const match = type.match(/^([^\x5b]*)((\x5b\d*\x5d)*)(\x5b(\d*)\x5d)$/);
  if (match) {
    return {
      base: match[1],
      index: match[2] + match[4],
      array: {
        base: match[1],
        prefix: match[1] + match[2],
        count: match[5] ? parseInt(match[5]) : -1
      }
    };
  }
  return { base: type };
}
var TypedDataEncoder = class _TypedDataEncoder {
  /**
   *  The primary type for the structured [[types]].
   *
   *  This is derived automatically from the [[types]], since no
   *  recursion is possible, once the DAG for the types is consturcted
   *  internally, the primary type must be the only remaining type with
   *  no parent nodes.
   */
  primaryType;
  #types;
  /**
   *  The types.
   */
  get types() {
    return JSON.parse(this.#types);
  }
  #fullTypes;
  #encoderCache;
  /**
   *  Create a new **TypedDataEncoder** for %%types%%.
   *
   *  This performs all necessary checking that types are valid and
   *  do not violate the [[link-eip-712]] structural constraints as
   *  well as computes the [[primaryType]].
   */
  constructor(_types) {
    this.#fullTypes = /* @__PURE__ */ new Map();
    this.#encoderCache = /* @__PURE__ */ new Map();
    const links = /* @__PURE__ */ new Map();
    const parents = /* @__PURE__ */ new Map();
    const subtypes = /* @__PURE__ */ new Map();
    const types = {};
    Object.keys(_types).forEach((type) => {
      types[type] = _types[type].map(({ name, type: type2 }) => {
        let { base, index } = splitArray(type2);
        if (base === "int" && !_types["int"]) {
          base = "int256";
        }
        if (base === "uint" && !_types["uint"]) {
          base = "uint256";
        }
        return { name, type: base + (index || "") };
      });
      links.set(type, /* @__PURE__ */ new Set());
      parents.set(type, []);
      subtypes.set(type, /* @__PURE__ */ new Set());
    });
    this.#types = JSON.stringify(types);
    for (const name in types) {
      const uniqueNames = /* @__PURE__ */ new Set();
      for (const field of types[name]) {
        assertArgument(!uniqueNames.has(field.name), `duplicate variable name ${JSON.stringify(field.name)} in ${JSON.stringify(name)}`, "types", _types);
        uniqueNames.add(field.name);
        const baseType = splitArray(field.type).base;
        assertArgument(baseType !== name, `circular type reference to ${JSON.stringify(baseType)}`, "types", _types);
        const encoder = getBaseEncoder(baseType);
        if (encoder) {
          continue;
        }
        assertArgument(parents.has(baseType), `unknown type ${JSON.stringify(baseType)}`, "types", _types);
        parents.get(baseType).push(name);
        links.get(name).add(baseType);
      }
    }
    const primaryTypes = Array.from(parents.keys()).filter((n) => parents.get(n).length === 0);
    assertArgument(primaryTypes.length !== 0, "missing primary type", "types", _types);
    assertArgument(primaryTypes.length === 1, `ambiguous primary types or unused types: ${primaryTypes.map((t) => JSON.stringify(t)).join(", ")}`, "types", _types);
    defineProperties(this, { primaryType: primaryTypes[0] });
    function checkCircular(type, found) {
      assertArgument(!found.has(type), `circular type reference to ${JSON.stringify(type)}`, "types", _types);
      found.add(type);
      for (const child of links.get(type)) {
        if (!parents.has(child)) {
          continue;
        }
        checkCircular(child, found);
        for (const subtype of found) {
          subtypes.get(subtype).add(child);
        }
      }
      found.delete(type);
    }
    checkCircular(this.primaryType, /* @__PURE__ */ new Set());
    for (const [name, set] of subtypes) {
      const st = Array.from(set);
      st.sort();
      this.#fullTypes.set(name, encodeType(name, types[name]) + st.map((t) => encodeType(t, types[t])).join(""));
    }
  }
  /**
   *  Returnthe encoder for the specific %%type%%.
   */
  getEncoder(type) {
    let encoder = this.#encoderCache.get(type);
    if (!encoder) {
      encoder = this.#getEncoder(type);
      this.#encoderCache.set(type, encoder);
    }
    return encoder;
  }
  #getEncoder(type) {
    {
      const encoder = getBaseEncoder(type);
      if (encoder) {
        return encoder;
      }
    }
    const array = splitArray(type).array;
    if (array) {
      const subtype = array.prefix;
      const subEncoder = this.getEncoder(subtype);
      return (value) => {
        assertArgument(array.count === -1 || array.count === value.length, `array length mismatch; expected length ${array.count}`, "value", value);
        let result = value.map(subEncoder);
        if (this.#fullTypes.has(subtype)) {
          result = result.map(keccak256);
        }
        return keccak256(concat(result));
      };
    }
    const fields = this.types[type];
    if (fields) {
      const encodedType = id(this.#fullTypes.get(type));
      return (value) => {
        const values = fields.map(({ name, type: type2 }) => {
          const result = this.getEncoder(type2)(value[name]);
          if (this.#fullTypes.has(type2)) {
            return keccak256(result);
          }
          return result;
        });
        values.unshift(encodedType);
        return concat(values);
      };
    }
    assertArgument(false, `unknown type: ${type}`, "type", type);
  }
  /**
   *  Return the full type for %%name%%.
   */
  encodeType(name) {
    const result = this.#fullTypes.get(name);
    assertArgument(result, `unknown type: ${JSON.stringify(name)}`, "name", name);
    return result;
  }
  /**
   *  Return the encoded %%value%% for the %%type%%.
   */
  encodeData(type, value) {
    return this.getEncoder(type)(value);
  }
  /**
   *  Returns the hash of %%value%% for the type of %%name%%.
   */
  hashStruct(name, value) {
    return keccak256(this.encodeData(name, value));
  }
  /**
   *  Return the fulled encoded %%value%% for the [[types]].
   */
  encode(value) {
    return this.encodeData(this.primaryType, value);
  }
  /**
   *  Return the hash of the fully encoded %%value%% for the [[types]].
   */
  hash(value) {
    return this.hashStruct(this.primaryType, value);
  }
  /**
   *  @_ignore:
   */
  _visit(type, value, callback) {
    {
      const encoder = getBaseEncoder(type);
      if (encoder) {
        return callback(type, value);
      }
    }
    const array = splitArray(type).array;
    if (array) {
      assertArgument(array.count === -1 || array.count === value.length, `array length mismatch; expected length ${array.count}`, "value", value);
      return value.map((v) => this._visit(array.prefix, v, callback));
    }
    const fields = this.types[type];
    if (fields) {
      return fields.reduce((accum, { name, type: type2 }) => {
        accum[name] = this._visit(type2, value[name], callback);
        return accum;
      }, {});
    }
    assertArgument(false, `unknown type: ${type}`, "type", type);
  }
  /**
   *  Call %%calback%% for each value in %%value%%, passing the type and
   *  component within %%value%%.
   *
   *  This is useful for replacing addresses or other transformation that
   *  may be desired on each component, based on its type.
   */
  visit(value, callback) {
    return this._visit(this.primaryType, value, callback);
  }
  /**
   *  Create a new **TypedDataEncoder** for %%types%%.
   */
  static from(types) {
    return new _TypedDataEncoder(types);
  }
  /**
   *  Return the primary type for %%types%%.
   */
  static getPrimaryType(types) {
    return _TypedDataEncoder.from(types).primaryType;
  }
  /**
   *  Return the hashed struct for %%value%% using %%types%% and %%name%%.
   */
  static hashStruct(name, types, value) {
    return _TypedDataEncoder.from(types).hashStruct(name, value);
  }
  /**
   *  Return the domain hash for %%domain%%.
   */
  static hashDomain(domain) {
    const domainFields = [];
    for (const name in domain) {
      if (domain[name] == null) {
        continue;
      }
      const type = domainFieldTypes[name];
      assertArgument(type, `invalid typed-data domain key: ${JSON.stringify(name)}`, "domain", domain);
      domainFields.push({ name, type });
    }
    domainFields.sort((a, b) => {
      return domainFieldNames.indexOf(a.name) - domainFieldNames.indexOf(b.name);
    });
    return _TypedDataEncoder.hashStruct("EIP712Domain", { EIP712Domain: domainFields }, domain);
  }
  /**
   *  Return the fully encoded [[link-eip-712]] %%value%% for %%types%% with %%domain%%.
   */
  static encode(domain, types, value) {
    return concat([
      "0x1901",
      _TypedDataEncoder.hashDomain(domain),
      _TypedDataEncoder.from(types).hash(value)
    ]);
  }
  /**
   *  Return the hash of the fully encoded [[link-eip-712]] %%value%% for %%types%% with %%domain%%.
   */
  static hash(domain, types, value) {
    return keccak256(_TypedDataEncoder.encode(domain, types, value));
  }
  // Replaces all address types with ENS names with their looked up address
  /**
   * Resolves to the value from resolving all addresses in %%value%% for
   * %%types%% and the %%domain%%.
   */
  static async resolveNames(domain, types, value, resolveName) {
    domain = Object.assign({}, domain);
    for (const key in domain) {
      if (domain[key] == null) {
        delete domain[key];
      }
    }
    const ensCache = {};
    if (domain.verifyingContract && !isHexString(domain.verifyingContract, 20)) {
      ensCache[domain.verifyingContract] = "0x";
    }
    const encoder = _TypedDataEncoder.from(types);
    encoder.visit(value, (type, value2) => {
      if (type === "address" && !isHexString(value2, 20)) {
        ensCache[value2] = "0x";
      }
      return value2;
    });
    for (const name in ensCache) {
      ensCache[name] = await resolveName(name);
    }
    if (domain.verifyingContract && ensCache[domain.verifyingContract]) {
      domain.verifyingContract = ensCache[domain.verifyingContract];
    }
    value = encoder.visit(value, (type, value2) => {
      if (type === "address" && ensCache[value2]) {
        return ensCache[value2];
      }
      return value2;
    });
    return { domain, value };
  }
  /**
   *  Returns the JSON-encoded payload expected by nodes which implement
   *  the JSON-RPC [[link-eip-712]] method.
   */
  static getPayload(domain, types, value) {
    _TypedDataEncoder.hashDomain(domain);
    const domainValues = {};
    const domainTypes = [];
    domainFieldNames.forEach((name) => {
      const value2 = domain[name];
      if (value2 == null) {
        return;
      }
      domainValues[name] = domainChecks[name](value2);
      domainTypes.push({ name, type: domainFieldTypes[name] });
    });
    const encoder = _TypedDataEncoder.from(types);
    types = encoder.types;
    const typesWithDomain = Object.assign({}, types);
    assertArgument(typesWithDomain.EIP712Domain == null, "types must not contain EIP712Domain type", "types.EIP712Domain", types);
    typesWithDomain.EIP712Domain = domainTypes;
    encoder.encode(value);
    return {
      types: typesWithDomain,
      domain: domainValues,
      primaryType: encoder.primaryType,
      message: encoder.visit(value, (type, value2) => {
        if (type.match(/^bytes(\d*)/)) {
          return hexlify(getBytes(value2));
        }
        if (type.match(/^u?int/)) {
          return getBigInt(value2).toString();
        }
        switch (type) {
          case "address":
            return value2.toLowerCase();
          case "bool":
            return !!value2;
          case "string":
            assertArgument(typeof value2 === "string", "invalid string", "value", value2);
            return value2;
        }
        assertArgument(false, "unsupported type", "type", type);
      })
    };
  }
};

// node_modules/ethers/lib.esm/providers/provider.js
var BN_07 = BigInt(0);
function copyRequest(req) {
  const result = {};
  if (req.to) {
    result.to = req.to;
  }
  if (req.from) {
    result.from = req.from;
  }
  if (req.data) {
    result.data = hexlify(req.data);
  }
  const bigIntKeys = "chainId,gasLimit,gasPrice,maxFeePerBlobGas,maxFeePerGas,maxPriorityFeePerGas,value".split(/,/);
  for (const key of bigIntKeys) {
    if (!(key in req) || req[key] == null) {
      continue;
    }
    result[key] = getBigInt(req[key], `request.${key}`);
  }
  const numberKeys = "type,nonce".split(/,/);
  for (const key of numberKeys) {
    if (!(key in req) || req[key] == null) {
      continue;
    }
    result[key] = getNumber(req[key], `request.${key}`);
  }
  if (req.accessList) {
    result.accessList = accessListify(req.accessList);
  }
  if (req.authorizationList) {
    result.authorizationList = req.authorizationList.slice();
  }
  if ("blockTag" in req) {
    result.blockTag = req.blockTag;
  }
  if ("enableCcipRead" in req) {
    result.enableCcipRead = !!req.enableCcipRead;
  }
  if ("customData" in req) {
    result.customData = req.customData;
  }
  if ("blobVersionedHashes" in req && req.blobVersionedHashes) {
    result.blobVersionedHashes = req.blobVersionedHashes.slice();
  }
  if ("kzg" in req) {
    result.kzg = req.kzg;
  }
  if ("blobWrapperVersion" in req) {
    result.blobWrapperVersion = req.blobWrapperVersion;
  }
  if ("blobs" in req && req.blobs) {
    result.blobs = req.blobs.map((b) => {
      if (isBytesLike(b)) {
        return hexlify(b);
      }
      return Object.assign({}, b);
    });
  }
  return result;
}

// node_modules/ethers/lib.esm/providers/abstract-signer.js
function checkProvider(signer, operation) {
  if (signer.provider) {
    return signer.provider;
  }
  assert(false, "missing provider", "UNSUPPORTED_OPERATION", { operation });
}
async function populate(signer, tx) {
  let pop = copyRequest(tx);
  if (pop.to != null) {
    pop.to = resolveAddress(pop.to, signer);
  }
  if (pop.from != null) {
    const from = pop.from;
    pop.from = Promise.all([
      signer.getAddress(),
      resolveAddress(from, signer)
    ]).then(([address, from2]) => {
      assertArgument(address.toLowerCase() === from2.toLowerCase(), "transaction from mismatch", "tx.from", from2);
      return address;
    });
  } else {
    pop.from = signer.getAddress();
  }
  return await resolveProperties(pop);
}
var AbstractSigner = class {
  /**
   *  The provider this signer is connected to.
   */
  provider;
  /**
   *  Creates a new Signer connected to %%provider%%.
   */
  constructor(provider) {
    defineProperties(this, { provider: provider || null });
  }
  async getNonce(blockTag) {
    return checkProvider(this, "getTransactionCount").getTransactionCount(await this.getAddress(), blockTag);
  }
  async populateCall(tx) {
    const pop = await populate(this, tx);
    return pop;
  }
  async populateTransaction(tx) {
    const provider = checkProvider(this, "populateTransaction");
    const pop = await populate(this, tx);
    if (pop.nonce == null) {
      pop.nonce = await this.getNonce("pending");
    }
    if (pop.gasLimit == null) {
      pop.gasLimit = await this.estimateGas(pop);
    }
    const network = await this.provider.getNetwork();
    if (pop.chainId != null) {
      const chainId = getBigInt(pop.chainId);
      assertArgument(chainId === network.chainId, "transaction chainId mismatch", "tx.chainId", tx.chainId);
    } else {
      pop.chainId = network.chainId;
    }
    const hasEip1559 = pop.maxFeePerGas != null || pop.maxPriorityFeePerGas != null;
    if (pop.gasPrice != null && (pop.type === 2 || hasEip1559)) {
      assertArgument(false, "eip-1559 transaction do not support gasPrice", "tx", tx);
    } else if ((pop.type === 0 || pop.type === 1) && hasEip1559) {
      assertArgument(false, "pre-eip-1559 transaction do not support maxFeePerGas/maxPriorityFeePerGas", "tx", tx);
    }
    if ((pop.type === 2 || pop.type == null) && (pop.maxFeePerGas != null && pop.maxPriorityFeePerGas != null)) {
      pop.type = 2;
    } else if (pop.type === 0 || pop.type === 1) {
      const feeData = await provider.getFeeData();
      assert(feeData.gasPrice != null, "network does not support gasPrice", "UNSUPPORTED_OPERATION", {
        operation: "getGasPrice"
      });
      if (pop.gasPrice == null) {
        pop.gasPrice = feeData.gasPrice;
      }
    } else {
      const feeData = await provider.getFeeData();
      if (pop.type == null) {
        if (feeData.maxFeePerGas != null && feeData.maxPriorityFeePerGas != null) {
          if (pop.authorizationList && pop.authorizationList.length) {
            pop.type = 4;
          } else {
            pop.type = 2;
          }
          if (pop.gasPrice != null) {
            const gasPrice = pop.gasPrice;
            delete pop.gasPrice;
            pop.maxFeePerGas = gasPrice;
            pop.maxPriorityFeePerGas = gasPrice;
          } else {
            if (pop.maxFeePerGas == null) {
              pop.maxFeePerGas = feeData.maxFeePerGas;
            }
            if (pop.maxPriorityFeePerGas == null) {
              pop.maxPriorityFeePerGas = feeData.maxPriorityFeePerGas;
            }
          }
        } else if (feeData.gasPrice != null) {
          assert(!hasEip1559, "network does not support EIP-1559", "UNSUPPORTED_OPERATION", {
            operation: "populateTransaction"
          });
          if (pop.gasPrice == null) {
            pop.gasPrice = feeData.gasPrice;
          }
          pop.type = 0;
        } else {
          assert(false, "failed to get consistent fee data", "UNSUPPORTED_OPERATION", {
            operation: "signer.getFeeData"
          });
        }
      } else if (pop.type === 2 || pop.type === 3 || pop.type === 4) {
        if (pop.maxFeePerGas == null) {
          pop.maxFeePerGas = feeData.maxFeePerGas;
        }
        if (pop.maxPriorityFeePerGas == null) {
          pop.maxPriorityFeePerGas = feeData.maxPriorityFeePerGas;
        }
      }
    }
    return await resolveProperties(pop);
  }
  async populateAuthorization(_auth) {
    const auth = Object.assign({}, _auth);
    if (auth.chainId == null) {
      auth.chainId = (await checkProvider(this, "getNetwork").getNetwork()).chainId;
    }
    if (auth.nonce == null) {
      auth.nonce = await this.getNonce();
    }
    return auth;
  }
  async estimateGas(tx) {
    return checkProvider(this, "estimateGas").estimateGas(await this.populateCall(tx));
  }
  async call(tx) {
    return checkProvider(this, "call").call(await this.populateCall(tx));
  }
  async resolveName(name) {
    const provider = checkProvider(this, "resolveName");
    return await provider.resolveName(name);
  }
  async sendTransaction(tx) {
    const provider = checkProvider(this, "sendTransaction");
    const pop = await this.populateTransaction(tx);
    delete pop.from;
    const txObj = Transaction.from(pop);
    return await provider.broadcastTransaction(await this.signTransaction(txObj));
  }
  // @TODO: in v7 move this to be abstract
  authorize(authorization) {
    assert(false, "authorization not implemented for this signer", "UNSUPPORTED_OPERATION", { operation: "authorize" });
  }
};
var VoidSigner = class _VoidSigner extends AbstractSigner {
  /**
   *  The signer address.
   */
  address;
  /**
   *  Creates a new **VoidSigner** with %%address%% attached to
   *  %%provider%%.
   */
  constructor(address, provider) {
    super(provider);
    defineProperties(this, { address });
  }
  async getAddress() {
    return this.address;
  }
  connect(provider) {
    return new _VoidSigner(this.address, provider);
  }
  #throwUnsupported(suffix, operation) {
    assert(false, `VoidSigner cannot sign ${suffix}`, "UNSUPPORTED_OPERATION", { operation });
  }
  async signTransaction(tx) {
    this.#throwUnsupported("transactions", "signTransaction");
  }
  async signMessage(message) {
    this.#throwUnsupported("messages", "signMessage");
  }
  async signTypedData(domain, types, value) {
    this.#throwUnsupported("typed-data", "signTypedData");
  }
};

// node_modules/ethers/lib.esm/wallet/base-wallet.js
var BaseWallet = class _BaseWallet extends AbstractSigner {
  /**
   *  The wallet address.
   */
  address;
  #signingKey;
  /**
   *  Creates a new BaseWallet for %%privateKey%%, optionally
   *  connected to %%provider%%.
   *
   *  If %%provider%% is not specified, only offline methods can
   *  be used.
   */
  constructor(privateKey, provider) {
    super(provider);
    assertArgument(privateKey && typeof privateKey.sign === "function", "invalid private key", "privateKey", "[ REDACTED ]");
    this.#signingKey = privateKey;
    const address = computeAddress(this.signingKey.publicKey);
    defineProperties(this, { address });
  }
  // Store private values behind getters to reduce visibility
  // in console.log
  /**
   *  The [[SigningKey]] used for signing payloads.
   */
  get signingKey() {
    return this.#signingKey;
  }
  /**
   *  The private key for this wallet.
   */
  get privateKey() {
    return this.signingKey.privateKey;
  }
  async getAddress() {
    return this.address;
  }
  connect(provider) {
    return new _BaseWallet(this.#signingKey, provider);
  }
  async signTransaction(tx) {
    tx = copyRequest(tx);
    const { to, from } = await resolveProperties({
      to: tx.to ? resolveAddress(tx.to, this) : void 0,
      from: tx.from ? resolveAddress(tx.from, this) : void 0
    });
    if (to != null) {
      tx.to = to;
    }
    if (from != null) {
      tx.from = from;
    }
    if (tx.from != null) {
      assertArgument(getAddress(tx.from) === this.address, "transaction from address mismatch", "tx.from", tx.from);
      delete tx.from;
    }
    const btx = Transaction.from(tx);
    btx.signature = this.signingKey.sign(btx.unsignedHash);
    return btx.serialized;
  }
  async signMessage(message) {
    return this.signMessageSync(message);
  }
  // @TODO: Add a secialized signTx and signTyped sync that enforces
  // all parameters are known?
  /**
   *  Returns the signature for %%message%% signed with this wallet.
   */
  signMessageSync(message) {
    return this.signingKey.sign(hashMessage(message)).serialized;
  }
  /**
   *  Returns the Authorization for %%auth%%.
   */
  authorizeSync(auth) {
    assertArgument(typeof auth.address === "string", "invalid address for authorizeSync", "auth.address", auth);
    const signature = this.signingKey.sign(hashAuthorization(auth));
    return Object.assign({}, {
      address: getAddress(auth.address),
      nonce: getBigInt(auth.nonce || 0),
      chainId: getBigInt(auth.chainId || 0)
    }, { signature });
  }
  /**
   *  Resolves to the Authorization for %%auth%%.
   */
  async authorize(auth) {
    auth = Object.assign({}, auth, {
      address: await resolveAddress(auth.address, this)
    });
    return this.authorizeSync(await this.populateAuthorization(auth));
  }
  async signTypedData(domain, types, value) {
    const populated = await TypedDataEncoder.resolveNames(domain, types, value, async (name) => {
      assert(this.provider != null, "cannot resolve ENS names without a provider", "UNSUPPORTED_OPERATION", {
        operation: "resolveName",
        info: { name }
      });
      const address = await this.provider.resolveName(name);
      assert(address != null, "unconfigured ENS name", "UNCONFIGURED_NAME", {
        value: name
      });
      return address;
    });
    return this.signingKey.sign(TypedDataEncoder.hash(populated.domain, types, populated.value)).serialized;
  }
};

// node_modules/ethers/lib.esm/wordlists/decode-owl.js
var subsChrs = " !#$%&'()*+,-./<=>?@[]^_`{|}~";
var Word = /^[a-z]*$/i;
function unfold(words7, sep) {
  let initial = 97;
  return words7.reduce((accum, word) => {
    if (word === sep) {
      initial++;
    } else if (word.match(Word)) {
      accum.push(String.fromCharCode(initial) + word);
    } else {
      initial = 97;
      accum.push(word);
    }
    return accum;
  }, []);
}
function decode(data4, subs) {
  for (let i = subsChrs.length - 1; i >= 0; i--) {
    data4 = data4.split(subsChrs[i]).join(subs.substring(2 * i, 2 * i + 2));
  }
  const clumps = [];
  const leftover = data4.replace(/(:|([0-9])|([A-Z][a-z]*))/g, (all, item, semi, word) => {
    if (semi) {
      for (let i = parseInt(semi); i >= 0; i--) {
        clumps.push(";");
      }
    } else {
      clumps.push(item.toLowerCase());
    }
    return "";
  });
  if (leftover) {
    throw new Error(`leftovers: ${JSON.stringify(leftover)}`);
  }
  return unfold(unfold(clumps, ";"), ":");
}
function decodeOwl(data4) {
  assertArgument(data4[0] === "0", "unsupported auwl data", "data", data4);
  return decode(data4.substring(1 + 2 * subsChrs.length), data4.substring(1, 1 + 2 * subsChrs.length));
}

// node_modules/ethers/lib.esm/wordlists/wordlist.js
var Wordlist = class {
  locale;
  /**
   *  Creates a new Wordlist instance.
   *
   *  Sub-classes MUST call this if they provide their own constructor,
   *  passing in the locale string of the language.
   *
   *  Generally there is no need to create instances of a Wordlist,
   *  since each language-specific Wordlist creates an instance and
   *  there is no state kept internally, so they are safe to share.
   */
  constructor(locale) {
    defineProperties(this, { locale });
  }
  /**
   *  Sub-classes may override this to provide a language-specific
   *  method for spliting %%phrase%% into individual words.
   *
   *  By default, %%phrase%% is split using any sequences of
   *  white-space as defined by regular expressions (i.e. ``/\s+/``).
   */
  split(phrase) {
    return phrase.toLowerCase().split(/\s+/g);
  }
  /**
   *  Sub-classes may override this to provider a language-specific
   *  method for joining %%words%% into a phrase.
   *
   *  By default, %%words%% are joined by a single space.
   */
  join(words7) {
    return words7.join(" ");
  }
};

// node_modules/ethers/lib.esm/wordlists/wordlist-owl.js
var WordlistOwl = class extends Wordlist {
  #data;
  #checksum;
  /**
   *  Creates a new Wordlist for %%locale%% using the OWL %%data%%
   *  and validated against the %%checksum%%.
   */
  constructor(locale, data4, checksum7) {
    super(locale);
    this.#data = data4;
    this.#checksum = checksum7;
    this.#words = null;
  }
  /**
   *  The OWL-encoded data.
   */
  get _data() {
    return this.#data;
  }
  /**
   *  Decode all the words for the wordlist.
   */
  _decodeWords() {
    return decodeOwl(this.#data);
  }
  #words;
  #loadWords() {
    if (this.#words == null) {
      const words7 = this._decodeWords();
      const checksum7 = id(words7.join("\n") + "\n");
      if (checksum7 !== this.#checksum) {
        throw new Error(`BIP39 Wordlist for ${this.locale} FAILED`);
      }
      this.#words = words7;
    }
    return this.#words;
  }
  getWord(index) {
    const words7 = this.#loadWords();
    assertArgument(index >= 0 && index < words7.length, `invalid word index: ${index}`, "index", index);
    return words7[index];
  }
  getWordIndex(word) {
    return this.#loadWords().indexOf(word);
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-en.js
var words = "0erleonalorenseinceregesticitStanvetearctssi#ch2Athck&tneLl0And#Il.yLeOutO=S|S%b/ra@SurdU'0Ce[Cid|CountCu'Hie=IdOu,-Qui*Ro[TT]T%T*[Tu$0AptDD-tD*[Ju,M.UltV<)Vi)0Rob-0FairF%dRaid0A(EEntRee0Ead0MRRp%tS!_rmBumCoholErtI&LLeyLowMo,O}PhaReadySoT Ways0A>urAz(gOngOuntU'd0Aly,Ch%Ci|G G!GryIm$K!Noun)Nu$O` Sw T&naTiqueXietyY1ArtOlogyPe?P!Pro=Ril1ChCt-EaEnaGueMMedM%MyOundR<+Re,Ri=RowTTefa@Ti,Tw%k0KPe@SaultSetSi,SumeThma0H!>OmTa{T&dT.udeTra@0Ct]D.Gu,NtTh%ToTumn0Era+OcadoOid0AkeA*AyEsomeFulKw?d0Is:ByChel%C#D+GL<)Lc#y~MbooN<aNn RRelyRga(R*lSeS-SketTt!3A^AnAutyCau'ComeEfF%eG(Ha=H(dLie=LowLtN^Nef./TrayTt Twe&Y#d3Cyc!DKeNdOlogyRdR`Tt _{AdeAmeAnketA,EakE[IndOodO[omOu'UeUrUsh_rdAtDyIlMbNeNusOkO,Rd R(gRrowSsTtomUn)XY_{etA(AndA[A=EadEezeI{Id+IefIghtIngIskOccoliOk&OnzeOomO` OwnUsh2Bb!DdyD+tFf$oIldLbLkL!tNd!Nk Rd&Rg R,SS(e[SyTt Y Zz:Bba+B(B!CtusGeKe~LmM aMpNN$N)lNdyNn#NoeNvasNy#Pab!P.$Pta(RRb#RdRgoRpetRryRtSeShS(o/!Su$TT$ogT^Teg%yTt!UghtU'Ut]Ve3Il(gL yM|NsusNturyRe$Rta(_irAlkAmp]An+AosApt Ar+A'AtEapE{Ee'EfErryE,I{&IefIldIm}yOi)Oo'R#-U{!UnkUrn0G?Nnam#Rc!Tiz&TyVil_imApArifyAwAyE<ErkEv I{I|IffImbIn-IpO{OgO'O`OudOwnUbUmpU, Ut^_^A,C#utDeFfeeIlInL!@L%LumnMb(eMeMf%tM-Mm#Mp<yNc tNdu@NfirmNg*[N}@Nsid NtrolNv()OkOlPp PyR$ReRnR*@/Tt#U^UntryUp!Ur'Us(V Yo>_{Ad!AftAmA}AshAt AwlAzyEamEd.EekEwI{etImeIspIt-OpO[Ou^OwdUci$UelUi'Umb!Un^UshYY,$2BeLtu*PPbo?dRiousRr|Rta(R=Sh]/omTe3C!:DMa+MpN)Ng R(gShUght WnY3AlBa>BrisCadeCemb CideCl(eC%a>C*a'ErF&'F(eFyG*eLayLiv M<dMi'Ni$Nti,NyP?tP&dPos.P`PutyRi=ScribeS tSignSkSpair/royTailTe@VelopVi)Vo>3AgramAlAm#dAryCeE'lEtFf G.$Gn.yLemmaNn NosaurRe@RtSag*eScov Sea'ShSmi[S%d Splay/<)V tVideV%)Zzy5Ct%Cum|G~Lph(Ma(Na>NkeyN%OrSeUb!Ve_ftAg#AmaA,-AwEamE[IftIllInkIpI=OpUmY2CkMbNeR(g/T^Ty1Arf1Nam-:G G!RlyRnR`Sily/Sy1HoOlogyOnomy0GeItUca>1F%t0G1GhtTh 2BowD E@r-Eg<tEm|Eph<tEvat%I>Se0B?kBodyBra)Er+Ot]PloyPow Pty0Ab!A@DD![D%'EmyErgyF%)Ga+G(eH<)JoyLi,OughR-hRollSu*T Ti*TryVelope1Isode0U$Uip0AA'OdeOs]R%Upt0CapeSayS&)Ta>0Ern$H-s1Id&)IlOkeOl=1A@Amp!Ce[Ch<+C.eCludeCu'Ecu>Erci'Hau,Hib.I!I,ItOt-P<dPe@Pi*Pla(Po'P*[T&dTra0EEbrow:Br-CeCultyDeIntI`~L'MeMilyMousNNcyNtasyRmSh]TT$Th TigueUltV%.e3Atu*Bru?yD $EEdElMa!N)/iv$T^V W3B Ct]EldGu*LeLmLt N$NdNeNg NishReRmR,Sc$ShTT}[X_gAmeAshAtAv%EeIghtIpOatO{O%Ow UidUshY_mCusGIlLd~owOdOtR)Re,R+tRkRtu}RumRw?dSsil/ UndX_gi!AmeEqu|EshI&dIn+OgOntO,OwnOz&U.2ElNNnyRna)RyTu*:D+tInLaxy~ yMePRa+Rba+Rd&Rl-Rm|SSpTeTh U+Ze3N $NiusN*Nt!Nu(e/u*2O,0AntFtGg!Ng RaffeRlVe_dAn)A*A[IdeImp'ObeOomOryO=OwUe_tDde[LdOdO'RillaSpelSsipV nWn_bA)A(AntApeA[Av.yEatE&IdIefItOc yOupOwUnt_rdE[IdeIltIt?N3M:B.IrLfMm M, NdPpyRb%RdRshR=,TVeWkZ?d3AdAl`ArtAvyD+hogIght~oLmetLpNRo3Dd&Gh~NtPRe/%y5BbyCkeyLdLeLiday~owMeNeyOdPeRnRr%R'Sp.$/TelUrV 5BGeM<Mb!M%Nd*dNgryNtRd!RryRtSb<d3Brid:1EOn0EaEntifyLe2N%e4LLeg$L}[0A+Ita>M&'Mu}Pa@Po'Pro=Pul'0ChCludeComeC*a'DexD-a>Do%Du,ryF<tFl-tF%mHa!H .Iti$Je@JuryMa>N Noc|PutQuiryS<eSe@SideSpi*/$lTa@T e,ToVe,V.eVol=3On0L<dOla>Sue0Em1Ory:CketGu?RZz3AlousAns~yWel9BInKeUr}yY5D+I)MpNg!Ni%Nk/:Ng?oo3EnEpT^upY3CkDD}yNdNgdomSsTT^&TeTt&Wi4EeIfeO{Ow:BBelB%Dd DyKeMpNgua+PtopR+T T(UghUndryVaWWnWsu.Y Zy3Ad AfArnA=Ctu*FtGG$G&dIsu*M#NdNg`NsOp?dSs#Tt Vel3ArB tyBr?yC&'FeFtGhtKeMbM.NkOnQuid/Tt!VeZ?d5AdAnB, C$CkG-NelyNgOpTt yUdUn+VeY$5CkyGga+Mb N?N^Xury3R-s:Ch(eDG-G}tIdIlInJ%KeMm$NNa+Nda>NgoNs]Nu$P!Rb!R^Rg(R(eRketRria+SkSs/ T^T i$ThTrixTt XimumZe3AdowAnAsu*AtCh<-D$DiaLodyLtMb M%yNt]NuRcyR+R.RryShSsa+T$Thod3Dd!DnightLk~]M-NdNimumN%Nu>Rac!Rr%S ySs/akeXXedXtu*5Bi!DelDifyMM|N.%NkeyN, N`OnR$ReRn(gSqu.oTh T]T%Unta(U'VeVie5ChFf(LeLtiplySc!SeumShroomS-/Tu$3Self/ yTh:I=MePk(Rrow/yT]Tu*3ArCkEdGati=G!@I` PhewR=/TTw%kUtr$V WsXt3CeGht5B!I'M(eeOd!Rm$R`SeTab!TeTh(gTi)VelW5C!?Mb R'T:K0EyJe@Li+Scu*S =Ta(Vious0CurE<Tob 0Or1FF Fi)T&2L1Ay0DI=Ymp-0It0CeEI#L(eLy1EnEraIn]Po'T]1An+B.Ch?dD D(?yG<I|Ig($Ph<0Tr-h0H 0Tdo%T TputTside0AlEnEr0NN 0Yg&0/ 0O}:CtDd!GeIrLa)LmNdaNelN-N` P RadeR|RkRrotRtySsT^ThTi|TrolTt nU'VeYm|3A)AnutArAs<tL-<NN$tyNcilOp!Pp Rfe@Rm.Rs#T2O}OtoRa'Ys-$0AnoCn-Ctu*E)GGe#~LotNkO} Pe/olT^Zza_)A}tA,-A>AyEa'Ed+U{UgUn+2EmEtIntL?LeLi)NdNyOlPul?Rt]S.]Ssib!/TatoTt yV tyWd W _@i)Ai'Ed-tEf Epa*Es|EttyEv|I)IdeIm?yIntI%.yIs#Iva>IzeOb!mO)[Odu)Of.OgramOje@Omo>OofOp tyOsp O>@OudOvide2Bl-Dd(g~LpL'Mpk(N^PilPpyR^a'R.yRpo'R'ShTZz!3Ramid:99Al.yAntumArt E,]I{ItIzO>:Bb.Cco#CeCkD?DioIlInI'~yMpN^NdomN+PidReTeTh V&WZ%3AdyAlAs#BelBuildC$lCei=CipeC%dCyc!Du)F!@F%mFu'G]G*tGul?Je@LaxLea'LiefLyMa(Memb M(dMo=Nd NewNtOp&PairPeatPla)P%tQui*ScueSemb!Si,Sour)Sp#'SultTi*T*atTurnUn]Ve$ViewW?d2Y`m0BBb#CeChDeD+F!GhtGidNgOtPp!SkTu$V$V 5AdA,BotBu,CketM<)OfOkieOmSeTa>UghUndU>Y$5Bb DeGLeNNwayR$:DDd!D}[FeIlLadLm#L#LtLu>MeMp!NdTisfyToshiU)Usa+VeY1A!AnA*Att E}HemeHoolI&)I[%sOrp]OutRapRe&RiptRub1AAr^As#AtC#dC*tCt]Cur.yEdEkGm|Le@~M(?Ni%N'Nt&)RiesRvi)Ss]Tt!TupV&_dowAftAllowA*EdEllEriffIeldIftI}IpIv O{OeOotOpOrtOuld O=RimpRugUff!Y0Bl(gCkDeE+GhtGnL|Lk~yLv Mil?Mp!N)NgR&/ Tua>XZe1A>Et^IIllInIrtUll0AbAmEepEnd I)IdeIghtImOg<OtOwUsh0AllArtI!OkeOo`0A{AkeApIffOw0ApCc Ci$CkDaFtL?Ldi LidLut]L=Me#eNgOnRryRtUlUndUpUr)U`0A)A*Ati$AwnEakEci$EedEllEndH eI)Id IkeInIr.L.OilOns%O#OrtOtRayReadR(gY0Ua*UeezeUir*l_b!AdiumAffA+AirsAmpAndArtA>AyEakEelEmEpE*oI{IllIngO{Oma^O}OolOryO=Ra>gyReetRikeR#gRugg!Ud|UffUmb!Y!0Bje@Bm.BwayC)[ChDd&Ff G?G+,ItMm NNnyN'tP PplyP*meReRfa)R+Rpri'RroundR=ySpe@/a(1AllowAmpApArmE?EetIftImIngIt^Ord1MbolMptomRup/em:B!Ck!GIlL|LkNkPeR+tSk/eTtooXi3A^Am~NN<tNnisNtRm/Xt_nkAtEmeEnE%yE*EyIngIsOughtReeRi=RowUmbUnd 0CketDeG LtMb MeNyPRedSsueT!5A,BaccoDayDdl EGe` I!tK&MatoM%rowNeNgueNightOlO`PP-Pp!R^RnadoRtoi'SsT$Uri,W?dW WnY_{AdeAff-Ag-A(Ansf ApAshA=lAyEatEeEndI$IbeI{Igg ImIpOphyOub!U{UeUlyUmpetU,U`Y2BeIt]Mb!NaN}lRkeyRnRt!1El=EntyI)InI,O1PeP-$:5Ly5B*lla0Ab!Awa*C!Cov D DoFairFoldHappyIf%mIqueItIv 'KnownLo{TilUsu$Veil1Da>GradeHoldOnP Set1B<Ge0A+EEdEfulE![U$0Il.y:C<tCuumGueLidL!yL=NNishP%Rious/Ult3H-!L=tNd%Ntu*NueRbRifyRs]RyS'lT <3Ab!Br<tCiousCt%yDeoEw~a+Nta+Ol(Rtu$RusSaS.Su$T$Vid5C$I)IdLc<oLumeTeYa+:GeG#ItLk~LnutNtRfa*RmRri%ShSp/eT VeY3Al`Ap#ArA'lA` BDd(gEk&dIrdLcome/T_!AtEatEelEnE*IpIsp 0DeD`FeLd~NNdowNeNgNkNn Nt ReSdomSeShT}[5LfM<Nd OdOlRdRkRldRryR`_pE{E,!I,I>Ong::Rd3Ar~ow9UUngU`:3BraRo9NeO";
var checksum = "0x3c8acc1e7b08d8e76f9fda015ef48dc8c710a73cb7e0f77b2c18a9b5a7adde60";
var wordlist = null;
var LangEn = class _LangEn extends WordlistOwl {
  /**
   *  Creates a new instance of the English language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langEn]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("en", words, checksum);
  }
  /**
   *  Returns a singleton instance of a ``LangEn``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist == null) {
      wordlist = new _LangEn();
    }
    return wordlist;
  }
};

// node_modules/ethers/lib.esm/wallet/mnemonic.js
function getUpperMask(bits) {
  return (1 << bits) - 1 << 8 - bits & 255;
}
function getLowerMask(bits) {
  return (1 << bits) - 1 & 255;
}
function mnemonicToEntropy(mnemonic, wordlist10) {
  assertNormalize("NFKD");
  if (wordlist10 == null) {
    wordlist10 = LangEn.wordlist();
  }
  const words7 = wordlist10.split(mnemonic);
  assertArgument(words7.length % 3 === 0 && words7.length >= 12 && words7.length <= 24, "invalid mnemonic length", "mnemonic", "[ REDACTED ]");
  const entropy = new Uint8Array(Math.ceil(11 * words7.length / 8));
  let offset = 0;
  for (let i = 0; i < words7.length; i++) {
    let index = wordlist10.getWordIndex(words7[i].normalize("NFKD"));
    assertArgument(index >= 0, `invalid mnemonic word at index ${i}`, "mnemonic", "[ REDACTED ]");
    for (let bit = 0; bit < 11; bit++) {
      if (index & 1 << 10 - bit) {
        entropy[offset >> 3] |= 1 << 7 - offset % 8;
      }
      offset++;
    }
  }
  const entropyBits = 32 * words7.length / 3;
  const checksumBits = words7.length / 3;
  const checksumMask = getUpperMask(checksumBits);
  const checksum7 = getBytes(sha2562(entropy.slice(0, entropyBits / 8)))[0] & checksumMask;
  assertArgument(checksum7 === (entropy[entropy.length - 1] & checksumMask), "invalid mnemonic checksum", "mnemonic", "[ REDACTED ]");
  return hexlify(entropy.slice(0, entropyBits / 8));
}
function entropyToMnemonic(entropy, wordlist10) {
  assertArgument(entropy.length % 4 === 0 && entropy.length >= 16 && entropy.length <= 32, "invalid entropy size", "entropy", "[ REDACTED ]");
  if (wordlist10 == null) {
    wordlist10 = LangEn.wordlist();
  }
  const indices = [0];
  let remainingBits = 11;
  for (let i = 0; i < entropy.length; i++) {
    if (remainingBits > 8) {
      indices[indices.length - 1] <<= 8;
      indices[indices.length - 1] |= entropy[i];
      remainingBits -= 8;
    } else {
      indices[indices.length - 1] <<= remainingBits;
      indices[indices.length - 1] |= entropy[i] >> 8 - remainingBits;
      indices.push(entropy[i] & getLowerMask(8 - remainingBits));
      remainingBits += 3;
    }
  }
  const checksumBits = entropy.length / 4;
  const checksum7 = parseInt(sha2562(entropy).substring(2, 4), 16) & getUpperMask(checksumBits);
  indices[indices.length - 1] <<= checksumBits;
  indices[indices.length - 1] |= checksum7 >> 8 - checksumBits;
  return wordlist10.join(indices.map((index) => wordlist10.getWord(index)));
}
var _guard2 = {};
var Mnemonic = class _Mnemonic {
  /**
   *  The mnemonic phrase of 12, 15, 18, 21 or 24 words.
   *
   *  Use the [[wordlist]] ``split`` method to get the individual words.
   */
  phrase;
  /**
   *  The password used for this mnemonic. If no password is used this
   *  is the empty string (i.e. ``""``) as per the specification.
   */
  password;
  /**
   *  The wordlist for this mnemonic.
   */
  wordlist;
  /**
   *  The underlying entropy which the mnemonic encodes.
   */
  entropy;
  /**
   *  @private
   */
  constructor(guard, entropy, phrase, password, wordlist10) {
    if (password == null) {
      password = "";
    }
    if (wordlist10 == null) {
      wordlist10 = LangEn.wordlist();
    }
    assertPrivate(guard, _guard2, "Mnemonic");
    defineProperties(this, { phrase, password, wordlist: wordlist10, entropy });
  }
  /**
   *  Returns the seed for the mnemonic.
   */
  computeSeed() {
    const salt = toUtf8Bytes("mnemonic" + this.password, "NFKD");
    return pbkdf2(toUtf8Bytes(this.phrase, "NFKD"), salt, 2048, 64, "sha512");
  }
  /**
   *  Creates a new Mnemonic for the %%phrase%%.
   *
   *  The default %%password%% is the empty string and the default
   *  wordlist is the [English wordlists](LangEn).
   */
  static fromPhrase(phrase, password, wordlist10) {
    const entropy = mnemonicToEntropy(phrase, wordlist10);
    phrase = entropyToMnemonic(getBytes(entropy), wordlist10);
    return new _Mnemonic(_guard2, entropy, phrase, password, wordlist10);
  }
  /**
   *  Create a new **Mnemonic** from the %%entropy%%.
   *
   *  The default %%password%% is the empty string and the default
   *  wordlist is the [English wordlists](LangEn).
   */
  static fromEntropy(_entropy, password, wordlist10) {
    const entropy = getBytes(_entropy, "entropy");
    const phrase = entropyToMnemonic(entropy, wordlist10);
    return new _Mnemonic(_guard2, hexlify(entropy), phrase, password, wordlist10);
  }
  /**
   *  Returns the phrase for %%mnemonic%%.
   */
  static entropyToPhrase(_entropy, wordlist10) {
    const entropy = getBytes(_entropy, "entropy");
    return entropyToMnemonic(entropy, wordlist10);
  }
  /**
   *  Returns the entropy for %%phrase%%.
   */
  static phraseToEntropy(phrase, wordlist10) {
    return mnemonicToEntropy(phrase, wordlist10);
  }
  /**
   *  Returns true if %%phrase%% is a valid [[link-bip-39]] phrase.
   *
   *  This checks all the provided words belong to the %%wordlist%%,
   *  that the length is valid and the checksum is correct.
   */
  static isValidMnemonic(phrase, wordlist10) {
    try {
      mnemonicToEntropy(phrase, wordlist10);
      return true;
    } catch (error) {
    }
    return false;
  }
};

// node_modules/aes-js/lib.esm/aes.js
var __classPrivateFieldGet = function(receiver, state, kind, f2) {
  if (kind === "a" && !f2) throw new TypeError("Private accessor was defined without a getter");
  if (typeof state === "function" ? receiver !== state || !f2 : !state.has(receiver)) throw new TypeError("Cannot read private member from an object whose class did not declare it");
  return kind === "m" ? f2 : kind === "a" ? f2.call(receiver) : f2 ? f2.value : state.get(receiver);
};
var __classPrivateFieldSet = function(receiver, state, value, kind, f2) {
  if (kind === "m") throw new TypeError("Private method is not writable");
  if (kind === "a" && !f2) throw new TypeError("Private accessor was defined without a setter");
  if (typeof state === "function" ? receiver !== state || !f2 : !state.has(receiver)) throw new TypeError("Cannot write private member to an object whose class did not declare it");
  return kind === "a" ? f2.call(receiver, value) : f2 ? f2.value = value : state.set(receiver, value), value;
};
var _AES_key;
var _AES_Kd;
var _AES_Ke;
var numberOfRounds = { 16: 10, 24: 12, 32: 14 };
var rcon = [1, 2, 4, 8, 16, 32, 64, 128, 27, 54, 108, 216, 171, 77, 154, 47, 94, 188, 99, 198, 151, 53, 106, 212, 179, 125, 250, 239, 197, 145];
var S = [99, 124, 119, 123, 242, 107, 111, 197, 48, 1, 103, 43, 254, 215, 171, 118, 202, 130, 201, 125, 250, 89, 71, 240, 173, 212, 162, 175, 156, 164, 114, 192, 183, 253, 147, 38, 54, 63, 247, 204, 52, 165, 229, 241, 113, 216, 49, 21, 4, 199, 35, 195, 24, 150, 5, 154, 7, 18, 128, 226, 235, 39, 178, 117, 9, 131, 44, 26, 27, 110, 90, 160, 82, 59, 214, 179, 41, 227, 47, 132, 83, 209, 0, 237, 32, 252, 177, 91, 106, 203, 190, 57, 74, 76, 88, 207, 208, 239, 170, 251, 67, 77, 51, 133, 69, 249, 2, 127, 80, 60, 159, 168, 81, 163, 64, 143, 146, 157, 56, 245, 188, 182, 218, 33, 16, 255, 243, 210, 205, 12, 19, 236, 95, 151, 68, 23, 196, 167, 126, 61, 100, 93, 25, 115, 96, 129, 79, 220, 34, 42, 144, 136, 70, 238, 184, 20, 222, 94, 11, 219, 224, 50, 58, 10, 73, 6, 36, 92, 194, 211, 172, 98, 145, 149, 228, 121, 231, 200, 55, 109, 141, 213, 78, 169, 108, 86, 244, 234, 101, 122, 174, 8, 186, 120, 37, 46, 28, 166, 180, 198, 232, 221, 116, 31, 75, 189, 139, 138, 112, 62, 181, 102, 72, 3, 246, 14, 97, 53, 87, 185, 134, 193, 29, 158, 225, 248, 152, 17, 105, 217, 142, 148, 155, 30, 135, 233, 206, 85, 40, 223, 140, 161, 137, 13, 191, 230, 66, 104, 65, 153, 45, 15, 176, 84, 187, 22];
var Si = [82, 9, 106, 213, 48, 54, 165, 56, 191, 64, 163, 158, 129, 243, 215, 251, 124, 227, 57, 130, 155, 47, 255, 135, 52, 142, 67, 68, 196, 222, 233, 203, 84, 123, 148, 50, 166, 194, 35, 61, 238, 76, 149, 11, 66, 250, 195, 78, 8, 46, 161, 102, 40, 217, 36, 178, 118, 91, 162, 73, 109, 139, 209, 37, 114, 248, 246, 100, 134, 104, 152, 22, 212, 164, 92, 204, 93, 101, 182, 146, 108, 112, 72, 80, 253, 237, 185, 218, 94, 21, 70, 87, 167, 141, 157, 132, 144, 216, 171, 0, 140, 188, 211, 10, 247, 228, 88, 5, 184, 179, 69, 6, 208, 44, 30, 143, 202, 63, 15, 2, 193, 175, 189, 3, 1, 19, 138, 107, 58, 145, 17, 65, 79, 103, 220, 234, 151, 242, 207, 206, 240, 180, 230, 115, 150, 172, 116, 34, 231, 173, 53, 133, 226, 249, 55, 232, 28, 117, 223, 110, 71, 241, 26, 113, 29, 41, 197, 137, 111, 183, 98, 14, 170, 24, 190, 27, 252, 86, 62, 75, 198, 210, 121, 32, 154, 219, 192, 254, 120, 205, 90, 244, 31, 221, 168, 51, 136, 7, 199, 49, 177, 18, 16, 89, 39, 128, 236, 95, 96, 81, 127, 169, 25, 181, 74, 13, 45, 229, 122, 159, 147, 201, 156, 239, 160, 224, 59, 77, 174, 42, 245, 176, 200, 235, 187, 60, 131, 83, 153, 97, 23, 43, 4, 126, 186, 119, 214, 38, 225, 105, 20, 99, 85, 33, 12, 125];
var T1 = [3328402341, 4168907908, 4000806809, 4135287693, 4294111757, 3597364157, 3731845041, 2445657428, 1613770832, 33620227, 3462883241, 1445669757, 3892248089, 3050821474, 1303096294, 3967186586, 2412431941, 528646813, 2311702848, 4202528135, 4026202645, 2992200171, 2387036105, 4226871307, 1101901292, 3017069671, 1604494077, 1169141738, 597466303, 1403299063, 3832705686, 2613100635, 1974974402, 3791519004, 1033081774, 1277568618, 1815492186, 2118074177, 4126668546, 2211236943, 1748251740, 1369810420, 3521504564, 4193382664, 3799085459, 2883115123, 1647391059, 706024767, 134480908, 2512897874, 1176707941, 2646852446, 806885416, 932615841, 168101135, 798661301, 235341577, 605164086, 461406363, 3756188221, 3454790438, 1311188841, 2142417613, 3933566367, 302582043, 495158174, 1479289972, 874125870, 907746093, 3698224818, 3025820398, 1537253627, 2756858614, 1983593293, 3084310113, 2108928974, 1378429307, 3722699582, 1580150641, 327451799, 2790478837, 3117535592, 0, 3253595436, 1075847264, 3825007647, 2041688520, 3059440621, 3563743934, 2378943302, 1740553945, 1916352843, 2487896798, 2555137236, 2958579944, 2244988746, 3151024235, 3320835882, 1336584933, 3992714006, 2252555205, 2588757463, 1714631509, 293963156, 2319795663, 3925473552, 67240454, 4269768577, 2689618160, 2017213508, 631218106, 1269344483, 2723238387, 1571005438, 2151694528, 93294474, 1066570413, 563977660, 1882732616, 4059428100, 1673313503, 2008463041, 2950355573, 1109467491, 537923632, 3858759450, 4260623118, 3218264685, 2177748300, 403442708, 638784309, 3287084079, 3193921505, 899127202, 2286175436, 773265209, 2479146071, 1437050866, 4236148354, 2050833735, 3362022572, 3126681063, 840505643, 3866325909, 3227541664, 427917720, 2655997905, 2749160575, 1143087718, 1412049534, 999329963, 193497219, 2353415882, 3354324521, 1807268051, 672404540, 2816401017, 3160301282, 369822493, 2916866934, 3688947771, 1681011286, 1949973070, 336202270, 2454276571, 201721354, 1210328172, 3093060836, 2680341085, 3184776046, 1135389935, 3294782118, 965841320, 831886756, 3554993207, 4068047243, 3588745010, 2345191491, 1849112409, 3664604599, 26054028, 2983581028, 2622377682, 1235855840, 3630984372, 2891339514, 4092916743, 3488279077, 3395642799, 4101667470, 1202630377, 268961816, 1874508501, 4034427016, 1243948399, 1546530418, 941366308, 1470539505, 1941222599, 2546386513, 3421038627, 2715671932, 3899946140, 1042226977, 2521517021, 1639824860, 227249030, 260737669, 3765465232, 2084453954, 1907733956, 3429263018, 2420656344, 100860677, 4160157185, 470683154, 3261161891, 1781871967, 2924959737, 1773779408, 394692241, 2579611992, 974986535, 664706745, 3655459128, 3958962195, 731420851, 571543859, 3530123707, 2849626480, 126783113, 865375399, 765172662, 1008606754, 361203602, 3387549984, 2278477385, 2857719295, 1344809080, 2782912378, 59542671, 1503764984, 160008576, 437062935, 1707065306, 3622233649, 2218934982, 3496503480, 2185314755, 697932208, 1512910199, 504303377, 2075177163, 2824099068, 1841019862, 739644986];
var T2 = [2781242211, 2230877308, 2582542199, 2381740923, 234877682, 3184946027, 2984144751, 1418839493, 1348481072, 50462977, 2848876391, 2102799147, 434634494, 1656084439, 3863849899, 2599188086, 1167051466, 2636087938, 1082771913, 2281340285, 368048890, 3954334041, 3381544775, 201060592, 3963727277, 1739838676, 4250903202, 3930435503, 3206782108, 4149453988, 2531553906, 1536934080, 3262494647, 484572669, 2923271059, 1783375398, 1517041206, 1098792767, 49674231, 1334037708, 1550332980, 4098991525, 886171109, 150598129, 2481090929, 1940642008, 1398944049, 1059722517, 201851908, 1385547719, 1699095331, 1587397571, 674240536, 2704774806, 252314885, 3039795866, 151914247, 908333586, 2602270848, 1038082786, 651029483, 1766729511, 3447698098, 2682942837, 454166793, 2652734339, 1951935532, 775166490, 758520603, 3000790638, 4004797018, 4217086112, 4137964114, 1299594043, 1639438038, 3464344499, 2068982057, 1054729187, 1901997871, 2534638724, 4121318227, 1757008337, 0, 750906861, 1614815264, 535035132, 3363418545, 3988151131, 3201591914, 1183697867, 3647454910, 1265776953, 3734260298, 3566750796, 3903871064, 1250283471, 1807470800, 717615087, 3847203498, 384695291, 3313910595, 3617213773, 1432761139, 2484176261, 3481945413, 283769337, 100925954, 2180939647, 4037038160, 1148730428, 3123027871, 3813386408, 4087501137, 4267549603, 3229630528, 2315620239, 2906624658, 3156319645, 1215313976, 82966005, 3747855548, 3245848246, 1974459098, 1665278241, 807407632, 451280895, 251524083, 1841287890, 1283575245, 337120268, 891687699, 801369324, 3787349855, 2721421207, 3431482436, 959321879, 1469301956, 4065699751, 2197585534, 1199193405, 2898814052, 3887750493, 724703513, 2514908019, 2696962144, 2551808385, 3516813135, 2141445340, 1715741218, 2119445034, 2872807568, 2198571144, 3398190662, 700968686, 3547052216, 1009259540, 2041044702, 3803995742, 487983883, 1991105499, 1004265696, 1449407026, 1316239930, 504629770, 3683797321, 168560134, 1816667172, 3837287516, 1570751170, 1857934291, 4014189740, 2797888098, 2822345105, 2754712981, 936633572, 2347923833, 852879335, 1133234376, 1500395319, 3084545389, 2348912013, 1689376213, 3533459022, 3762923945, 3034082412, 4205598294, 133428468, 634383082, 2949277029, 2398386810, 3913789102, 403703816, 3580869306, 2297460856, 1867130149, 1918643758, 607656988, 4049053350, 3346248884, 1368901318, 600565992, 2090982877, 2632479860, 557719327, 3717614411, 3697393085, 2249034635, 2232388234, 2430627952, 1115438654, 3295786421, 2865522278, 3633334344, 84280067, 33027830, 303828494, 2747425121, 1600795957, 4188952407, 3496589753, 2434238086, 1486471617, 658119965, 3106381470, 953803233, 334231800, 3005978776, 857870609, 3151128937, 1890179545, 2298973838, 2805175444, 3056442267, 574365214, 2450884487, 550103529, 1233637070, 4289353045, 2018519080, 2057691103, 2399374476, 4166623649, 2148108681, 387583245, 3664101311, 836232934, 3330556482, 3100665960, 3280093505, 2955516313, 2002398509, 287182607, 3413881008, 4238890068, 3597515707, 975967766];
var T3 = [1671808611, 2089089148, 2006576759, 2072901243, 4061003762, 1807603307, 1873927791, 3310653893, 810573872, 16974337, 1739181671, 729634347, 4263110654, 3613570519, 2883997099, 1989864566, 3393556426, 2191335298, 3376449993, 2106063485, 4195741690, 1508618841, 1204391495, 4027317232, 2917941677, 3563566036, 2734514082, 2951366063, 2629772188, 2767672228, 1922491506, 3227229120, 3082974647, 4246528509, 2477669779, 644500518, 911895606, 1061256767, 4144166391, 3427763148, 878471220, 2784252325, 3845444069, 4043897329, 1905517169, 3631459288, 827548209, 356461077, 67897348, 3344078279, 593839651, 3277757891, 405286936, 2527147926, 84871685, 2595565466, 118033927, 305538066, 2157648768, 3795705826, 3945188843, 661212711, 2999812018, 1973414517, 152769033, 2208177539, 745822252, 439235610, 455947803, 1857215598, 1525593178, 2700827552, 1391895634, 994932283, 3596728278, 3016654259, 695947817, 3812548067, 795958831, 2224493444, 1408607827, 3513301457, 0, 3979133421, 543178784, 4229948412, 2982705585, 1542305371, 1790891114, 3410398667, 3201918910, 961245753, 1256100938, 1289001036, 1491644504, 3477767631, 3496721360, 4012557807, 2867154858, 4212583931, 1137018435, 1305975373, 861234739, 2241073541, 1171229253, 4178635257, 33948674, 2139225727, 1357946960, 1011120188, 2679776671, 2833468328, 1374921297, 2751356323, 1086357568, 2408187279, 2460827538, 2646352285, 944271416, 4110742005, 3168756668, 3066132406, 3665145818, 560153121, 271589392, 4279952895, 4077846003, 3530407890, 3444343245, 202643468, 322250259, 3962553324, 1608629855, 2543990167, 1154254916, 389623319, 3294073796, 2817676711, 2122513534, 1028094525, 1689045092, 1575467613, 422261273, 1939203699, 1621147744, 2174228865, 1339137615, 3699352540, 577127458, 712922154, 2427141008, 2290289544, 1187679302, 3995715566, 3100863416, 339486740, 3732514782, 1591917662, 186455563, 3681988059, 3762019296, 844522546, 978220090, 169743370, 1239126601, 101321734, 611076132, 1558493276, 3260915650, 3547250131, 2901361580, 1655096418, 2443721105, 2510565781, 3828863972, 2039214713, 3878868455, 3359869896, 928607799, 1840765549, 2374762893, 3580146133, 1322425422, 2850048425, 1823791212, 1459268694, 4094161908, 3928346602, 1706019429, 2056189050, 2934523822, 135794696, 3134549946, 2022240376, 628050469, 779246638, 472135708, 2800834470, 3032970164, 3327236038, 3894660072, 3715932637, 1956440180, 522272287, 1272813131, 3185336765, 2340818315, 2323976074, 1888542832, 1044544574, 3049550261, 1722469478, 1222152264, 50660867, 4127324150, 236067854, 1638122081, 895445557, 1475980887, 3117443513, 2257655686, 3243809217, 489110045, 2662934430, 3778599393, 4162055160, 2561878936, 288563729, 1773916777, 3648039385, 2391345038, 2493985684, 2612407707, 505560094, 2274497927, 3911240169, 3460925390, 1442818645, 678973480, 3749357023, 2358182796, 2717407649, 2306869641, 219617805, 3218761151, 3862026214, 1120306242, 1756942440, 1103331905, 2578459033, 762796589, 252780047, 2966125488, 1425844308, 3151392187, 372911126];
var T4 = [1667474886, 2088535288, 2004326894, 2071694838, 4075949567, 1802223062, 1869591006, 3318043793, 808472672, 16843522, 1734846926, 724270422, 4278065639, 3621216949, 2880169549, 1987484396, 3402253711, 2189597983, 3385409673, 2105378810, 4210693615, 1499065266, 1195886990, 4042263547, 2913856577, 3570689971, 2728590687, 2947541573, 2627518243, 2762274643, 1920112356, 3233831835, 3082273397, 4261223649, 2475929149, 640051788, 909531756, 1061110142, 4160160501, 3435941763, 875846760, 2779116625, 3857003729, 4059105529, 1903268834, 3638064043, 825316194, 353713962, 67374088, 3351728789, 589522246, 3284360861, 404236336, 2526454071, 84217610, 2593830191, 117901582, 303183396, 2155911963, 3806477791, 3958056653, 656894286, 2998062463, 1970642922, 151591698, 2206440989, 741110872, 437923380, 454765878, 1852748508, 1515908788, 2694904667, 1381168804, 993742198, 3604373943, 3014905469, 690584402, 3823320797, 791638366, 2223281939, 1398011302, 3520161977, 0, 3991743681, 538992704, 4244381667, 2981218425, 1532751286, 1785380564, 3419096717, 3200178535, 960056178, 1246420628, 1280103576, 1482221744, 3486468741, 3503319995, 4025428677, 2863326543, 4227536621, 1128514950, 1296947098, 859002214, 2240123921, 1162203018, 4193849577, 33687044, 2139062782, 1347481760, 1010582648, 2678045221, 2829640523, 1364325282, 2745433693, 1077985408, 2408548869, 2459086143, 2644360225, 943212656, 4126475505, 3166494563, 3065430391, 3671750063, 555836226, 269496352, 4294908645, 4092792573, 3537006015, 3452783745, 202118168, 320025894, 3974901699, 1600119230, 2543297077, 1145359496, 387397934, 3301201811, 2812801621, 2122220284, 1027426170, 1684319432, 1566435258, 421079858, 1936954854, 1616945344, 2172753945, 1330631070, 3705438115, 572679748, 707427924, 2425400123, 2290647819, 1179044492, 4008585671, 3099120491, 336870440, 3739122087, 1583276732, 185277718, 3688593069, 3772791771, 842159716, 976899700, 168435220, 1229577106, 101059084, 606366792, 1549591736, 3267517855, 3553849021, 2897014595, 1650632388, 2442242105, 2509612081, 3840161747, 2038008818, 3890688725, 3368567691, 926374254, 1835907034, 2374863873, 3587531953, 1313788572, 2846482505, 1819063512, 1448540844, 4109633523, 3941213647, 1701162954, 2054852340, 2930698567, 134748176, 3132806511, 2021165296, 623210314, 774795868, 471606328, 2795958615, 3031746419, 3334885783, 3907527627, 3722280097, 1953799400, 522133822, 1263263126, 3183336545, 2341176845, 2324333839, 1886425312, 1044267644, 3048588401, 1718004428, 1212733584, 50529542, 4143317495, 235803164, 1633788866, 892690282, 1465383342, 3115962473, 2256965911, 3250673817, 488449850, 2661202215, 3789633753, 4177007595, 2560144171, 286339874, 1768537042, 3654906025, 2391705863, 2492770099, 2610673197, 505291324, 2273808917, 3924369609, 3469625735, 1431699370, 673740880, 3755965093, 2358021891, 2711746649, 2307489801, 218961690, 3217021541, 3873845719, 1111672452, 1751693520, 1094828930, 2576986153, 757954394, 252645662, 2964376443, 1414855848, 3149649517, 370555436];
var T5 = [1374988112, 2118214995, 437757123, 975658646, 1001089995, 530400753, 2902087851, 1273168787, 540080725, 2910219766, 2295101073, 4110568485, 1340463100, 3307916247, 641025152, 3043140495, 3736164937, 632953703, 1172967064, 1576976609, 3274667266, 2169303058, 2370213795, 1809054150, 59727847, 361929877, 3211623147, 2505202138, 3569255213, 1484005843, 1239443753, 2395588676, 1975683434, 4102977912, 2572697195, 666464733, 3202437046, 4035489047, 3374361702, 2110667444, 1675577880, 3843699074, 2538681184, 1649639237, 2976151520, 3144396420, 4269907996, 4178062228, 1883793496, 2403728665, 2497604743, 1383856311, 2876494627, 1917518562, 3810496343, 1716890410, 3001755655, 800440835, 2261089178, 3543599269, 807962610, 599762354, 33778362, 3977675356, 2328828971, 2809771154, 4077384432, 1315562145, 1708848333, 101039829, 3509871135, 3299278474, 875451293, 2733856160, 92987698, 2767645557, 193195065, 1080094634, 1584504582, 3178106961, 1042385657, 2531067453, 3711829422, 1306967366, 2438237621, 1908694277, 67556463, 1615861247, 429456164, 3602770327, 2302690252, 1742315127, 2968011453, 126454664, 3877198648, 2043211483, 2709260871, 2084704233, 4169408201, 0, 159417987, 841739592, 504459436, 1817866830, 4245618683, 260388950, 1034867998, 908933415, 168810852, 1750902305, 2606453969, 607530554, 202008497, 2472011535, 3035535058, 463180190, 2160117071, 1641816226, 1517767529, 470948374, 3801332234, 3231722213, 1008918595, 303765277, 235474187, 4069246893, 766945465, 337553864, 1475418501, 2943682380, 4003061179, 2743034109, 4144047775, 1551037884, 1147550661, 1543208500, 2336434550, 3408119516, 3069049960, 3102011747, 3610369226, 1113818384, 328671808, 2227573024, 2236228733, 3535486456, 2935566865, 3341394285, 496906059, 3702665459, 226906860, 2009195472, 733156972, 2842737049, 294930682, 1206477858, 2835123396, 2700099354, 1451044056, 573804783, 2269728455, 3644379585, 2362090238, 2564033334, 2801107407, 2776292904, 3669462566, 1068351396, 742039012, 1350078989, 1784663195, 1417561698, 4136440770, 2430122216, 775550814, 2193862645, 2673705150, 1775276924, 1876241833, 3475313331, 3366754619, 270040487, 3902563182, 3678124923, 3441850377, 1851332852, 3969562369, 2203032232, 3868552805, 2868897406, 566021896, 4011190502, 3135740889, 1248802510, 3936291284, 699432150, 832877231, 708780849, 3332740144, 899835584, 1951317047, 4236429990, 3767586992, 866637845, 4043610186, 1106041591, 2144161806, 395441711, 1984812685, 1139781709, 3433712980, 3835036895, 2664543715, 1282050075, 3240894392, 1181045119, 2640243204, 25965917, 4203181171, 4211818798, 3009879386, 2463879762, 3910161971, 1842759443, 2597806476, 933301370, 1509430414, 3943906441, 3467192302, 3076639029, 3776767469, 2051518780, 2631065433, 1441952575, 404016761, 1942435775, 1408749034, 1610459739, 3745345300, 2017778566, 3400528769, 3110650942, 941896748, 3265478751, 371049330, 3168937228, 675039627, 4279080257, 967311729, 135050206, 3635733660, 1683407248, 2076935265, 3576870512, 1215061108, 3501741890];
var T6 = [1347548327, 1400783205, 3273267108, 2520393566, 3409685355, 4045380933, 2880240216, 2471224067, 1428173050, 4138563181, 2441661558, 636813900, 4233094615, 3620022987, 2149987652, 2411029155, 1239331162, 1730525723, 2554718734, 3781033664, 46346101, 310463728, 2743944855, 3328955385, 3875770207, 2501218972, 3955191162, 3667219033, 768917123, 3545789473, 692707433, 1150208456, 1786102409, 2029293177, 1805211710, 3710368113, 3065962831, 401639597, 1724457132, 3028143674, 409198410, 2196052529, 1620529459, 1164071807, 3769721975, 2226875310, 486441376, 2499348523, 1483753576, 428819965, 2274680428, 3075636216, 598438867, 3799141122, 1474502543, 711349675, 129166120, 53458370, 2592523643, 2782082824, 4063242375, 2988687269, 3120694122, 1559041666, 730517276, 2460449204, 4042459122, 2706270690, 3446004468, 3573941694, 533804130, 2328143614, 2637442643, 2695033685, 839224033, 1973745387, 957055980, 2856345839, 106852767, 1371368976, 4181598602, 1033297158, 2933734917, 1179510461, 3046200461, 91341917, 1862534868, 4284502037, 605657339, 2547432937, 3431546947, 2003294622, 3182487618, 2282195339, 954669403, 3682191598, 1201765386, 3917234703, 3388507166, 0, 2198438022, 1211247597, 2887651696, 1315723890, 4227665663, 1443857720, 507358933, 657861945, 1678381017, 560487590, 3516619604, 975451694, 2970356327, 261314535, 3535072918, 2652609425, 1333838021, 2724322336, 1767536459, 370938394, 182621114, 3854606378, 1128014560, 487725847, 185469197, 2918353863, 3106780840, 3356761769, 2237133081, 1286567175, 3152976349, 4255350624, 2683765030, 3160175349, 3309594171, 878443390, 1988838185, 3704300486, 1756818940, 1673061617, 3403100636, 272786309, 1075025698, 545572369, 2105887268, 4174560061, 296679730, 1841768865, 1260232239, 4091327024, 3960309330, 3497509347, 1814803222, 2578018489, 4195456072, 575138148, 3299409036, 446754879, 3629546796, 4011996048, 3347532110, 3252238545, 4270639778, 915985419, 3483825537, 681933534, 651868046, 2755636671, 3828103837, 223377554, 2607439820, 1649704518, 3270937875, 3901806776, 1580087799, 4118987695, 3198115200, 2087309459, 2842678573, 3016697106, 1003007129, 2802849917, 1860738147, 2077965243, 164439672, 4100872472, 32283319, 2827177882, 1709610350, 2125135846, 136428751, 3874428392, 3652904859, 3460984630, 3572145929, 3593056380, 2939266226, 824852259, 818324884, 3224740454, 930369212, 2801566410, 2967507152, 355706840, 1257309336, 4148292826, 243256656, 790073846, 2373340630, 1296297904, 1422699085, 3756299780, 3818836405, 457992840, 3099667487, 2135319889, 77422314, 1560382517, 1945798516, 788204353, 1521706781, 1385356242, 870912086, 325965383, 2358957921, 2050466060, 2388260884, 2313884476, 4006521127, 901210569, 3990953189, 1014646705, 1503449823, 1062597235, 2031621326, 3212035895, 3931371469, 1533017514, 350174575, 2256028891, 2177544179, 1052338372, 741876788, 1606591296, 1914052035, 213705253, 2334669897, 1107234197, 1899603969, 3725069491, 2631447780, 2422494913, 1635502980, 1893020342, 1950903388, 1120974935];
var T7 = [2807058932, 1699970625, 2764249623, 1586903591, 1808481195, 1173430173, 1487645946, 59984867, 4199882800, 1844882806, 1989249228, 1277555970, 3623636965, 3419915562, 1149249077, 2744104290, 1514790577, 459744698, 244860394, 3235995134, 1963115311, 4027744588, 2544078150, 4190530515, 1608975247, 2627016082, 2062270317, 1507497298, 2200818878, 567498868, 1764313568, 3359936201, 2305455554, 2037970062, 1047239e3, 1910319033, 1337376481, 2904027272, 2892417312, 984907214, 1243112415, 830661914, 861968209, 2135253587, 2011214180, 2927934315, 2686254721, 731183368, 1750626376, 4246310725, 1820824798, 4172763771, 3542330227, 48394827, 2404901663, 2871682645, 671593195, 3254988725, 2073724613, 145085239, 2280796200, 2779915199, 1790575107, 2187128086, 472615631, 3029510009, 4075877127, 3802222185, 4107101658, 3201631749, 1646252340, 4270507174, 1402811438, 1436590835, 3778151818, 3950355702, 3963161475, 4020912224, 2667994737, 273792366, 2331590177, 104699613, 95345982, 3175501286, 2377486676, 1560637892, 3564045318, 369057872, 4213447064, 3919042237, 1137477952, 2658625497, 1119727848, 2340947849, 1530455833, 4007360968, 172466556, 266959938, 516552836, 0, 2256734592, 3980931627, 1890328081, 1917742170, 4294704398, 945164165, 3575528878, 958871085, 3647212047, 2787207260, 1423022939, 775562294, 1739656202, 3876557655, 2530391278, 2443058075, 3310321856, 547512796, 1265195639, 437656594, 3121275539, 719700128, 3762502690, 387781147, 218828297, 3350065803, 2830708150, 2848461854, 428169201, 122466165, 3720081049, 1627235199, 648017665, 4122762354, 1002783846, 2117360635, 695634755, 3336358691, 4234721005, 4049844452, 3704280881, 2232435299, 574624663, 287343814, 612205898, 1039717051, 840019705, 2708326185, 793451934, 821288114, 1391201670, 3822090177, 376187827, 3113855344, 1224348052, 1679968233, 2361698556, 1058709744, 752375421, 2431590963, 1321699145, 3519142200, 2734591178, 188127444, 2177869557, 3727205754, 2384911031, 3215212461, 2648976442, 2450346104, 3432737375, 1180849278, 331544205, 3102249176, 4150144569, 2952102595, 2159976285, 2474404304, 766078933, 313773861, 2570832044, 2108100632, 1668212892, 3145456443, 2013908262, 418672217, 3070356634, 2594734927, 1852171925, 3867060991, 3473416636, 3907448597, 2614737639, 919489135, 164948639, 2094410160, 2997825956, 590424639, 2486224549, 1723872674, 3157750862, 3399941250, 3501252752, 3625268135, 2555048196, 3673637356, 1343127501, 4130281361, 3599595085, 2957853679, 1297403050, 81781910, 3051593425, 2283490410, 532201772, 1367295589, 3926170974, 895287692, 1953757831, 1093597963, 492483431, 3528626907, 1446242576, 1192455638, 1636604631, 209336225, 344873464, 1015671571, 669961897, 3375740769, 3857572124, 2973530695, 3747192018, 1933530610, 3464042516, 935293895, 3454686199, 2858115069, 1863638845, 3683022916, 4085369519, 3292445032, 875313188, 1080017571, 3279033885, 621591778, 1233856572, 2504130317, 24197544, 3017672716, 3835484340, 3247465558, 2220981195, 3060847922, 1551124588, 1463996600];
var T8 = [4104605777, 1097159550, 396673818, 660510266, 2875968315, 2638606623, 4200115116, 3808662347, 821712160, 1986918061, 3430322568, 38544885, 3856137295, 718002117, 893681702, 1654886325, 2975484382, 3122358053, 3926825029, 4274053469, 796197571, 1290801793, 1184342925, 3556361835, 2405426947, 2459735317, 1836772287, 1381620373, 3196267988, 1948373848, 3764988233, 3385345166, 3263785589, 2390325492, 1480485785, 3111247143, 3780097726, 2293045232, 548169417, 3459953789, 3746175075, 439452389, 1362321559, 1400849762, 1685577905, 1806599355, 2174754046, 137073913, 1214797936, 1174215055, 3731654548, 2079897426, 1943217067, 1258480242, 529487843, 1437280870, 3945269170, 3049390895, 3313212038, 923313619, 679998e3, 3215307299, 57326082, 377642221, 3474729866, 2041877159, 133361907, 1776460110, 3673476453, 96392454, 878845905, 2801699524, 777231668, 4082475170, 2330014213, 4142626212, 2213296395, 1626319424, 1906247262, 1846563261, 562755902, 3708173718, 1040559837, 3871163981, 1418573201, 3294430577, 114585348, 1343618912, 2566595609, 3186202582, 1078185097, 3651041127, 3896688048, 2307622919, 425408743, 3371096953, 2081048481, 1108339068, 2216610296, 0, 2156299017, 736970802, 292596766, 1517440620, 251657213, 2235061775, 2933202493, 758720310, 265905162, 1554391400, 1532285339, 908999204, 174567692, 1474760595, 4002861748, 2610011675, 3234156416, 3693126241, 2001430874, 303699484, 2478443234, 2687165888, 585122620, 454499602, 151849742, 2345119218, 3064510765, 514443284, 4044981591, 1963412655, 2581445614, 2137062819, 19308535, 1928707164, 1715193156, 4219352155, 1126790795, 600235211, 3992742070, 3841024952, 836553431, 1669664834, 2535604243, 3323011204, 1243905413, 3141400786, 4180808110, 698445255, 2653899549, 2989552604, 2253581325, 3252932727, 3004591147, 1891211689, 2487810577, 3915653703, 4237083816, 4030667424, 2100090966, 865136418, 1229899655, 953270745, 3399679628, 3557504664, 4118925222, 2061379749, 3079546586, 2915017791, 983426092, 2022837584, 1607244650, 2118541908, 2366882550, 3635996816, 972512814, 3283088770, 1568718495, 3499326569, 3576539503, 621982671, 2895723464, 410887952, 2623762152, 1002142683, 645401037, 1494807662, 2595684844, 1335535747, 2507040230, 4293295786, 3167684641, 367585007, 3885750714, 1865862730, 2668221674, 2960971305, 2763173681, 1059270954, 2777952454, 2724642869, 1320957812, 2194319100, 2429595872, 2815956275, 77089521, 3973773121, 3444575871, 2448830231, 1305906550, 4021308739, 2857194700, 2516901860, 3518358430, 1787304780, 740276417, 1699839814, 1592394909, 2352307457, 2272556026, 188821243, 1729977011, 3687994002, 274084841, 3594982253, 3613494426, 2701949495, 4162096729, 322734571, 2837966542, 1640576439, 484830689, 1202797690, 3537852828, 4067639125, 349075736, 3342319475, 4157467219, 4255800159, 1030690015, 1155237496, 2951971274, 1757691577, 607398968, 2738905026, 499347990, 3794078908, 1011452712, 227885567, 2818666809, 213114376, 3034881240, 1455525988, 3414450555, 850817237, 1817998408, 3092726480];
var U1 = [0, 235474187, 470948374, 303765277, 941896748, 908933415, 607530554, 708780849, 1883793496, 2118214995, 1817866830, 1649639237, 1215061108, 1181045119, 1417561698, 1517767529, 3767586992, 4003061179, 4236429990, 4069246893, 3635733660, 3602770327, 3299278474, 3400528769, 2430122216, 2664543715, 2362090238, 2193862645, 2835123396, 2801107407, 3035535058, 3135740889, 3678124923, 3576870512, 3341394285, 3374361702, 3810496343, 3977675356, 4279080257, 4043610186, 2876494627, 2776292904, 3076639029, 3110650942, 2472011535, 2640243204, 2403728665, 2169303058, 1001089995, 899835584, 666464733, 699432150, 59727847, 226906860, 530400753, 294930682, 1273168787, 1172967064, 1475418501, 1509430414, 1942435775, 2110667444, 1876241833, 1641816226, 2910219766, 2743034109, 2976151520, 3211623147, 2505202138, 2606453969, 2302690252, 2269728455, 3711829422, 3543599269, 3240894392, 3475313331, 3843699074, 3943906441, 4178062228, 4144047775, 1306967366, 1139781709, 1374988112, 1610459739, 1975683434, 2076935265, 1775276924, 1742315127, 1034867998, 866637845, 566021896, 800440835, 92987698, 193195065, 429456164, 395441711, 1984812685, 2017778566, 1784663195, 1683407248, 1315562145, 1080094634, 1383856311, 1551037884, 101039829, 135050206, 437757123, 337553864, 1042385657, 807962610, 573804783, 742039012, 2531067453, 2564033334, 2328828971, 2227573024, 2935566865, 2700099354, 3001755655, 3168937228, 3868552805, 3902563182, 4203181171, 4102977912, 3736164937, 3501741890, 3265478751, 3433712980, 1106041591, 1340463100, 1576976609, 1408749034, 2043211483, 2009195472, 1708848333, 1809054150, 832877231, 1068351396, 766945465, 599762354, 159417987, 126454664, 361929877, 463180190, 2709260871, 2943682380, 3178106961, 3009879386, 2572697195, 2538681184, 2236228733, 2336434550, 3509871135, 3745345300, 3441850377, 3274667266, 3910161971, 3877198648, 4110568485, 4211818798, 2597806476, 2497604743, 2261089178, 2295101073, 2733856160, 2902087851, 3202437046, 2968011453, 3936291284, 3835036895, 4136440770, 4169408201, 3535486456, 3702665459, 3467192302, 3231722213, 2051518780, 1951317047, 1716890410, 1750902305, 1113818384, 1282050075, 1584504582, 1350078989, 168810852, 67556463, 371049330, 404016761, 841739592, 1008918595, 775550814, 540080725, 3969562369, 3801332234, 4035489047, 4269907996, 3569255213, 3669462566, 3366754619, 3332740144, 2631065433, 2463879762, 2160117071, 2395588676, 2767645557, 2868897406, 3102011747, 3069049960, 202008497, 33778362, 270040487, 504459436, 875451293, 975658646, 675039627, 641025152, 2084704233, 1917518562, 1615861247, 1851332852, 1147550661, 1248802510, 1484005843, 1451044056, 933301370, 967311729, 733156972, 632953703, 260388950, 25965917, 328671808, 496906059, 1206477858, 1239443753, 1543208500, 1441952575, 2144161806, 1908694277, 1675577880, 1842759443, 3610369226, 3644379585, 3408119516, 3307916247, 4011190502, 3776767469, 4077384432, 4245618683, 2809771154, 2842737049, 3144396420, 3043140495, 2673705150, 2438237621, 2203032232, 2370213795];
var U2 = [0, 185469197, 370938394, 487725847, 741876788, 657861945, 975451694, 824852259, 1483753576, 1400783205, 1315723890, 1164071807, 1950903388, 2135319889, 1649704518, 1767536459, 2967507152, 3152976349, 2801566410, 2918353863, 2631447780, 2547432937, 2328143614, 2177544179, 3901806776, 3818836405, 4270639778, 4118987695, 3299409036, 3483825537, 3535072918, 3652904859, 2077965243, 1893020342, 1841768865, 1724457132, 1474502543, 1559041666, 1107234197, 1257309336, 598438867, 681933534, 901210569, 1052338372, 261314535, 77422314, 428819965, 310463728, 3409685355, 3224740454, 3710368113, 3593056380, 3875770207, 3960309330, 4045380933, 4195456072, 2471224067, 2554718734, 2237133081, 2388260884, 3212035895, 3028143674, 2842678573, 2724322336, 4138563181, 4255350624, 3769721975, 3955191162, 3667219033, 3516619604, 3431546947, 3347532110, 2933734917, 2782082824, 3099667487, 3016697106, 2196052529, 2313884476, 2499348523, 2683765030, 1179510461, 1296297904, 1347548327, 1533017514, 1786102409, 1635502980, 2087309459, 2003294622, 507358933, 355706840, 136428751, 53458370, 839224033, 957055980, 605657339, 790073846, 2373340630, 2256028891, 2607439820, 2422494913, 2706270690, 2856345839, 3075636216, 3160175349, 3573941694, 3725069491, 3273267108, 3356761769, 4181598602, 4063242375, 4011996048, 3828103837, 1033297158, 915985419, 730517276, 545572369, 296679730, 446754879, 129166120, 213705253, 1709610350, 1860738147, 1945798516, 2029293177, 1239331162, 1120974935, 1606591296, 1422699085, 4148292826, 4233094615, 3781033664, 3931371469, 3682191598, 3497509347, 3446004468, 3328955385, 2939266226, 2755636671, 3106780840, 2988687269, 2198438022, 2282195339, 2501218972, 2652609425, 1201765386, 1286567175, 1371368976, 1521706781, 1805211710, 1620529459, 2105887268, 1988838185, 533804130, 350174575, 164439672, 46346101, 870912086, 954669403, 636813900, 788204353, 2358957921, 2274680428, 2592523643, 2441661558, 2695033685, 2880240216, 3065962831, 3182487618, 3572145929, 3756299780, 3270937875, 3388507166, 4174560061, 4091327024, 4006521127, 3854606378, 1014646705, 930369212, 711349675, 560487590, 272786309, 457992840, 106852767, 223377554, 1678381017, 1862534868, 1914052035, 2031621326, 1211247597, 1128014560, 1580087799, 1428173050, 32283319, 182621114, 401639597, 486441376, 768917123, 651868046, 1003007129, 818324884, 1503449823, 1385356242, 1333838021, 1150208456, 1973745387, 2125135846, 1673061617, 1756818940, 2970356327, 3120694122, 2802849917, 2887651696, 2637442643, 2520393566, 2334669897, 2149987652, 3917234703, 3799141122, 4284502037, 4100872472, 3309594171, 3460984630, 3545789473, 3629546796, 2050466060, 1899603969, 1814803222, 1730525723, 1443857720, 1560382517, 1075025698, 1260232239, 575138148, 692707433, 878443390, 1062597235, 243256656, 91341917, 409198410, 325965383, 3403100636, 3252238545, 3704300486, 3620022987, 3874428392, 3990953189, 4042459122, 4227665663, 2460449204, 2578018489, 2226875310, 2411029155, 3198115200, 3046200461, 2827177882, 2743944855];
var U3 = [0, 218828297, 437656594, 387781147, 875313188, 958871085, 775562294, 590424639, 1750626376, 1699970625, 1917742170, 2135253587, 1551124588, 1367295589, 1180849278, 1265195639, 3501252752, 3720081049, 3399941250, 3350065803, 3835484340, 3919042237, 4270507174, 4085369519, 3102249176, 3051593425, 2734591178, 2952102595, 2361698556, 2177869557, 2530391278, 2614737639, 3145456443, 3060847922, 2708326185, 2892417312, 2404901663, 2187128086, 2504130317, 2555048196, 3542330227, 3727205754, 3375740769, 3292445032, 3876557655, 3926170974, 4246310725, 4027744588, 1808481195, 1723872674, 1910319033, 2094410160, 1608975247, 1391201670, 1173430173, 1224348052, 59984867, 244860394, 428169201, 344873464, 935293895, 984907214, 766078933, 547512796, 1844882806, 1627235199, 2011214180, 2062270317, 1507497298, 1423022939, 1137477952, 1321699145, 95345982, 145085239, 532201772, 313773861, 830661914, 1015671571, 731183368, 648017665, 3175501286, 2957853679, 2807058932, 2858115069, 2305455554, 2220981195, 2474404304, 2658625497, 3575528878, 3625268135, 3473416636, 3254988725, 3778151818, 3963161475, 4213447064, 4130281361, 3599595085, 3683022916, 3432737375, 3247465558, 3802222185, 4020912224, 4172763771, 4122762354, 3201631749, 3017672716, 2764249623, 2848461854, 2331590177, 2280796200, 2431590963, 2648976442, 104699613, 188127444, 472615631, 287343814, 840019705, 1058709744, 671593195, 621591778, 1852171925, 1668212892, 1953757831, 2037970062, 1514790577, 1463996600, 1080017571, 1297403050, 3673637356, 3623636965, 3235995134, 3454686199, 4007360968, 3822090177, 4107101658, 4190530515, 2997825956, 3215212461, 2830708150, 2779915199, 2256734592, 2340947849, 2627016082, 2443058075, 172466556, 122466165, 273792366, 492483431, 1047239e3, 861968209, 612205898, 695634755, 1646252340, 1863638845, 2013908262, 1963115311, 1446242576, 1530455833, 1277555970, 1093597963, 1636604631, 1820824798, 2073724613, 1989249228, 1436590835, 1487645946, 1337376481, 1119727848, 164948639, 81781910, 331544205, 516552836, 1039717051, 821288114, 669961897, 719700128, 2973530695, 3157750862, 2871682645, 2787207260, 2232435299, 2283490410, 2667994737, 2450346104, 3647212047, 3564045318, 3279033885, 3464042516, 3980931627, 3762502690, 4150144569, 4199882800, 3070356634, 3121275539, 2904027272, 2686254721, 2200818878, 2384911031, 2570832044, 2486224549, 3747192018, 3528626907, 3310321856, 3359936201, 3950355702, 3867060991, 4049844452, 4234721005, 1739656202, 1790575107, 2108100632, 1890328081, 1402811438, 1586903591, 1233856572, 1149249077, 266959938, 48394827, 369057872, 418672217, 1002783846, 919489135, 567498868, 752375421, 209336225, 24197544, 376187827, 459744698, 945164165, 895287692, 574624663, 793451934, 1679968233, 1764313568, 2117360635, 1933530610, 1343127501, 1560637892, 1243112415, 1192455638, 3704280881, 3519142200, 3336358691, 3419915562, 3907448597, 3857572124, 4075877127, 4294704398, 3029510009, 3113855344, 2927934315, 2744104290, 2159976285, 2377486676, 2594734927, 2544078150];
var U4 = [0, 151849742, 303699484, 454499602, 607398968, 758720310, 908999204, 1059270954, 1214797936, 1097159550, 1517440620, 1400849762, 1817998408, 1699839814, 2118541908, 2001430874, 2429595872, 2581445614, 2194319100, 2345119218, 3034881240, 3186202582, 2801699524, 2951971274, 3635996816, 3518358430, 3399679628, 3283088770, 4237083816, 4118925222, 4002861748, 3885750714, 1002142683, 850817237, 698445255, 548169417, 529487843, 377642221, 227885567, 77089521, 1943217067, 2061379749, 1640576439, 1757691577, 1474760595, 1592394909, 1174215055, 1290801793, 2875968315, 2724642869, 3111247143, 2960971305, 2405426947, 2253581325, 2638606623, 2487810577, 3808662347, 3926825029, 4044981591, 4162096729, 3342319475, 3459953789, 3576539503, 3693126241, 1986918061, 2137062819, 1685577905, 1836772287, 1381620373, 1532285339, 1078185097, 1229899655, 1040559837, 923313619, 740276417, 621982671, 439452389, 322734571, 137073913, 19308535, 3871163981, 4021308739, 4104605777, 4255800159, 3263785589, 3414450555, 3499326569, 3651041127, 2933202493, 2815956275, 3167684641, 3049390895, 2330014213, 2213296395, 2566595609, 2448830231, 1305906550, 1155237496, 1607244650, 1455525988, 1776460110, 1626319424, 2079897426, 1928707164, 96392454, 213114376, 396673818, 514443284, 562755902, 679998e3, 865136418, 983426092, 3708173718, 3557504664, 3474729866, 3323011204, 4180808110, 4030667424, 3945269170, 3794078908, 2507040230, 2623762152, 2272556026, 2390325492, 2975484382, 3092726480, 2738905026, 2857194700, 3973773121, 3856137295, 4274053469, 4157467219, 3371096953, 3252932727, 3673476453, 3556361835, 2763173681, 2915017791, 3064510765, 3215307299, 2156299017, 2307622919, 2459735317, 2610011675, 2081048481, 1963412655, 1846563261, 1729977011, 1480485785, 1362321559, 1243905413, 1126790795, 878845905, 1030690015, 645401037, 796197571, 274084841, 425408743, 38544885, 188821243, 3613494426, 3731654548, 3313212038, 3430322568, 4082475170, 4200115116, 3780097726, 3896688048, 2668221674, 2516901860, 2366882550, 2216610296, 3141400786, 2989552604, 2837966542, 2687165888, 1202797690, 1320957812, 1437280870, 1554391400, 1669664834, 1787304780, 1906247262, 2022837584, 265905162, 114585348, 499347990, 349075736, 736970802, 585122620, 972512814, 821712160, 2595684844, 2478443234, 2293045232, 2174754046, 3196267988, 3079546586, 2895723464, 2777952454, 3537852828, 3687994002, 3234156416, 3385345166, 4142626212, 4293295786, 3841024952, 3992742070, 174567692, 57326082, 410887952, 292596766, 777231668, 660510266, 1011452712, 893681702, 1108339068, 1258480242, 1343618912, 1494807662, 1715193156, 1865862730, 1948373848, 2100090966, 2701949495, 2818666809, 3004591147, 3122358053, 2235061775, 2352307457, 2535604243, 2653899549, 3915653703, 3764988233, 4219352155, 4067639125, 3444575871, 3294430577, 3746175075, 3594982253, 836553431, 953270745, 600235211, 718002117, 367585007, 484830689, 133361907, 251657213, 2041877159, 1891211689, 1806599355, 1654886325, 1568718495, 1418573201, 1335535747, 1184342925];
function convertToInt32(bytes2) {
  const result = [];
  for (let i = 0; i < bytes2.length; i += 4) {
    result.push(bytes2[i] << 24 | bytes2[i + 1] << 16 | bytes2[i + 2] << 8 | bytes2[i + 3]);
  }
  return result;
}
var AES = class _AES {
  get key() {
    return __classPrivateFieldGet(this, _AES_key, "f").slice();
  }
  constructor(key) {
    _AES_key.set(this, void 0);
    _AES_Kd.set(this, void 0);
    _AES_Ke.set(this, void 0);
    if (!(this instanceof _AES)) {
      throw Error("AES must be instanitated with `new`");
    }
    __classPrivateFieldSet(this, _AES_key, new Uint8Array(key), "f");
    const rounds = numberOfRounds[this.key.length];
    if (rounds == null) {
      throw new TypeError("invalid key size (must be 16, 24 or 32 bytes)");
    }
    __classPrivateFieldSet(this, _AES_Ke, [], "f");
    __classPrivateFieldSet(this, _AES_Kd, [], "f");
    for (let i = 0; i <= rounds; i++) {
      __classPrivateFieldGet(this, _AES_Ke, "f").push([0, 0, 0, 0]);
      __classPrivateFieldGet(this, _AES_Kd, "f").push([0, 0, 0, 0]);
    }
    const roundKeyCount = (rounds + 1) * 4;
    const KC = this.key.length / 4;
    const tk = convertToInt32(this.key);
    let index;
    for (let i = 0; i < KC; i++) {
      index = i >> 2;
      __classPrivateFieldGet(this, _AES_Ke, "f")[index][i % 4] = tk[i];
      __classPrivateFieldGet(this, _AES_Kd, "f")[rounds - index][i % 4] = tk[i];
    }
    let rconpointer = 0;
    let t = KC, tt;
    while (t < roundKeyCount) {
      tt = tk[KC - 1];
      tk[0] ^= S[tt >> 16 & 255] << 24 ^ S[tt >> 8 & 255] << 16 ^ S[tt & 255] << 8 ^ S[tt >> 24 & 255] ^ rcon[rconpointer] << 24;
      rconpointer += 1;
      if (KC != 8) {
        for (let i2 = 1; i2 < KC; i2++) {
          tk[i2] ^= tk[i2 - 1];
        }
      } else {
        for (let i2 = 1; i2 < KC / 2; i2++) {
          tk[i2] ^= tk[i2 - 1];
        }
        tt = tk[KC / 2 - 1];
        tk[KC / 2] ^= S[tt & 255] ^ S[tt >> 8 & 255] << 8 ^ S[tt >> 16 & 255] << 16 ^ S[tt >> 24 & 255] << 24;
        for (let i2 = KC / 2 + 1; i2 < KC; i2++) {
          tk[i2] ^= tk[i2 - 1];
        }
      }
      let i = 0, r, c;
      while (i < KC && t < roundKeyCount) {
        r = t >> 2;
        c = t % 4;
        __classPrivateFieldGet(this, _AES_Ke, "f")[r][c] = tk[i];
        __classPrivateFieldGet(this, _AES_Kd, "f")[rounds - r][c] = tk[i++];
        t++;
      }
    }
    for (let r = 1; r < rounds; r++) {
      for (let c = 0; c < 4; c++) {
        tt = __classPrivateFieldGet(this, _AES_Kd, "f")[r][c];
        __classPrivateFieldGet(this, _AES_Kd, "f")[r][c] = U1[tt >> 24 & 255] ^ U2[tt >> 16 & 255] ^ U3[tt >> 8 & 255] ^ U4[tt & 255];
      }
    }
  }
  encrypt(plaintext) {
    if (plaintext.length != 16) {
      throw new TypeError("invalid plaintext size (must be 16 bytes)");
    }
    const rounds = __classPrivateFieldGet(this, _AES_Ke, "f").length - 1;
    const a = [0, 0, 0, 0];
    let t = convertToInt32(plaintext);
    for (let i = 0; i < 4; i++) {
      t[i] ^= __classPrivateFieldGet(this, _AES_Ke, "f")[0][i];
    }
    for (let r = 1; r < rounds; r++) {
      for (let i = 0; i < 4; i++) {
        a[i] = T1[t[i] >> 24 & 255] ^ T2[t[(i + 1) % 4] >> 16 & 255] ^ T3[t[(i + 2) % 4] >> 8 & 255] ^ T4[t[(i + 3) % 4] & 255] ^ __classPrivateFieldGet(this, _AES_Ke, "f")[r][i];
      }
      t = a.slice();
    }
    const result = new Uint8Array(16);
    let tt = 0;
    for (let i = 0; i < 4; i++) {
      tt = __classPrivateFieldGet(this, _AES_Ke, "f")[rounds][i];
      result[4 * i] = (S[t[i] >> 24 & 255] ^ tt >> 24) & 255;
      result[4 * i + 1] = (S[t[(i + 1) % 4] >> 16 & 255] ^ tt >> 16) & 255;
      result[4 * i + 2] = (S[t[(i + 2) % 4] >> 8 & 255] ^ tt >> 8) & 255;
      result[4 * i + 3] = (S[t[(i + 3) % 4] & 255] ^ tt) & 255;
    }
    return result;
  }
  decrypt(ciphertext) {
    if (ciphertext.length != 16) {
      throw new TypeError("invalid ciphertext size (must be 16 bytes)");
    }
    const rounds = __classPrivateFieldGet(this, _AES_Kd, "f").length - 1;
    const a = [0, 0, 0, 0];
    let t = convertToInt32(ciphertext);
    for (let i = 0; i < 4; i++) {
      t[i] ^= __classPrivateFieldGet(this, _AES_Kd, "f")[0][i];
    }
    for (let r = 1; r < rounds; r++) {
      for (let i = 0; i < 4; i++) {
        a[i] = T5[t[i] >> 24 & 255] ^ T6[t[(i + 3) % 4] >> 16 & 255] ^ T7[t[(i + 2) % 4] >> 8 & 255] ^ T8[t[(i + 1) % 4] & 255] ^ __classPrivateFieldGet(this, _AES_Kd, "f")[r][i];
      }
      t = a.slice();
    }
    const result = new Uint8Array(16);
    let tt = 0;
    for (let i = 0; i < 4; i++) {
      tt = __classPrivateFieldGet(this, _AES_Kd, "f")[rounds][i];
      result[4 * i] = (Si[t[i] >> 24 & 255] ^ tt >> 24) & 255;
      result[4 * i + 1] = (Si[t[(i + 3) % 4] >> 16 & 255] ^ tt >> 16) & 255;
      result[4 * i + 2] = (Si[t[(i + 2) % 4] >> 8 & 255] ^ tt >> 8) & 255;
      result[4 * i + 3] = (Si[t[(i + 1) % 4] & 255] ^ tt) & 255;
    }
    return result;
  }
};
_AES_key = /* @__PURE__ */ new WeakMap(), _AES_Kd = /* @__PURE__ */ new WeakMap(), _AES_Ke = /* @__PURE__ */ new WeakMap();

// node_modules/aes-js/lib.esm/mode.js
var ModeOfOperation = class {
  constructor(name, key, cls) {
    if (cls && !(this instanceof cls)) {
      throw new Error(`${name} must be instantiated with "new"`);
    }
    Object.defineProperties(this, {
      aes: { enumerable: true, value: new AES(key) },
      name: { enumerable: true, value: name }
    });
  }
};

// node_modules/aes-js/lib.esm/mode-cbc.js
var _CBC_iv;
var _CBC_lastBlock;
_CBC_iv = /* @__PURE__ */ new WeakMap(), _CBC_lastBlock = /* @__PURE__ */ new WeakMap();

// node_modules/aes-js/lib.esm/mode-cfb.js
var __classPrivateFieldGet2 = function(receiver, state, kind, f2) {
  if (kind === "a" && !f2) throw new TypeError("Private accessor was defined without a getter");
  if (typeof state === "function" ? receiver !== state || !f2 : !state.has(receiver)) throw new TypeError("Cannot read private member from an object whose class did not declare it");
  return kind === "m" ? f2 : kind === "a" ? f2.call(receiver) : f2 ? f2.value : state.get(receiver);
};
var _CFB_instances;
var _CFB_iv;
var _CFB_shiftRegister;
var _CFB_shift;
_CFB_iv = /* @__PURE__ */ new WeakMap(), _CFB_shiftRegister = /* @__PURE__ */ new WeakMap(), _CFB_instances = /* @__PURE__ */ new WeakSet(), _CFB_shift = function _CFB_shift2(data4) {
  const segmentSize = this.segmentSize / 8;
  __classPrivateFieldGet2(this, _CFB_shiftRegister, "f").set(__classPrivateFieldGet2(this, _CFB_shiftRegister, "f").subarray(segmentSize));
  __classPrivateFieldGet2(this, _CFB_shiftRegister, "f").set(data4.subarray(0, segmentSize), 16 - segmentSize);
};

// node_modules/aes-js/lib.esm/mode-ctr.js
var __classPrivateFieldSet2 = function(receiver, state, value, kind, f2) {
  if (kind === "m") throw new TypeError("Private method is not writable");
  if (kind === "a" && !f2) throw new TypeError("Private accessor was defined without a setter");
  if (typeof state === "function" ? receiver !== state || !f2 : !state.has(receiver)) throw new TypeError("Cannot write private member to an object whose class did not declare it");
  return kind === "a" ? f2.call(receiver, value) : f2 ? f2.value = value : state.set(receiver, value), value;
};
var __classPrivateFieldGet3 = function(receiver, state, kind, f2) {
  if (kind === "a" && !f2) throw new TypeError("Private accessor was defined without a getter");
  if (typeof state === "function" ? receiver !== state || !f2 : !state.has(receiver)) throw new TypeError("Cannot read private member from an object whose class did not declare it");
  return kind === "m" ? f2 : kind === "a" ? f2.call(receiver) : f2 ? f2.value : state.get(receiver);
};
var _CTR_remaining;
var _CTR_remainingIndex;
var _CTR_counter;
var CTR = class _CTR extends ModeOfOperation {
  constructor(key, initialValue) {
    super("CTR", key, _CTR);
    _CTR_remaining.set(this, void 0);
    _CTR_remainingIndex.set(this, void 0);
    _CTR_counter.set(this, void 0);
    __classPrivateFieldSet2(this, _CTR_counter, new Uint8Array(16), "f");
    __classPrivateFieldGet3(this, _CTR_counter, "f").fill(0);
    __classPrivateFieldSet2(this, _CTR_remaining, __classPrivateFieldGet3(this, _CTR_counter, "f"), "f");
    __classPrivateFieldSet2(this, _CTR_remainingIndex, 16, "f");
    if (initialValue == null) {
      initialValue = 1;
    }
    if (typeof initialValue === "number") {
      this.setCounterValue(initialValue);
    } else {
      this.setCounterBytes(initialValue);
    }
  }
  get counter() {
    return new Uint8Array(__classPrivateFieldGet3(this, _CTR_counter, "f"));
  }
  setCounterValue(value) {
    if (!Number.isInteger(value) || value < 0 || value > Number.MAX_SAFE_INTEGER) {
      throw new TypeError("invalid counter initial integer value");
    }
    for (let index = 15; index >= 0; --index) {
      __classPrivateFieldGet3(this, _CTR_counter, "f")[index] = value % 256;
      value = Math.floor(value / 256);
    }
  }
  setCounterBytes(value) {
    if (value.length !== 16) {
      throw new TypeError("invalid counter initial Uint8Array value length");
    }
    __classPrivateFieldGet3(this, _CTR_counter, "f").set(value);
  }
  increment() {
    for (let i = 15; i >= 0; i--) {
      if (__classPrivateFieldGet3(this, _CTR_counter, "f")[i] === 255) {
        __classPrivateFieldGet3(this, _CTR_counter, "f")[i] = 0;
      } else {
        __classPrivateFieldGet3(this, _CTR_counter, "f")[i]++;
        break;
      }
    }
  }
  encrypt(plaintext) {
    var _a, _b;
    const crypttext = new Uint8Array(plaintext);
    for (let i = 0; i < crypttext.length; i++) {
      if (__classPrivateFieldGet3(this, _CTR_remainingIndex, "f") === 16) {
        __classPrivateFieldSet2(this, _CTR_remaining, this.aes.encrypt(__classPrivateFieldGet3(this, _CTR_counter, "f")), "f");
        __classPrivateFieldSet2(this, _CTR_remainingIndex, 0, "f");
        this.increment();
      }
      crypttext[i] ^= __classPrivateFieldGet3(this, _CTR_remaining, "f")[__classPrivateFieldSet2(this, _CTR_remainingIndex, (_b = __classPrivateFieldGet3(this, _CTR_remainingIndex, "f"), _a = _b++, _b), "f"), _a];
    }
    return crypttext;
  }
  decrypt(ciphertext) {
    return this.encrypt(ciphertext);
  }
};
_CTR_remaining = /* @__PURE__ */ new WeakMap(), _CTR_remainingIndex = /* @__PURE__ */ new WeakMap(), _CTR_counter = /* @__PURE__ */ new WeakMap();

// node_modules/aes-js/lib.esm/mode-ofb.js
var _OFB_iv;
var _OFB_lastPrecipher;
var _OFB_lastPrecipherIndex;
_OFB_iv = /* @__PURE__ */ new WeakMap(), _OFB_lastPrecipher = /* @__PURE__ */ new WeakMap(), _OFB_lastPrecipherIndex = /* @__PURE__ */ new WeakMap();

// node_modules/ethers/lib.esm/wallet/utils.js
function zpad(value, length) {
  value = String(value);
  while (value.length < length) {
    value = "0" + value;
  }
  return value;
}
function getPassword(password) {
  if (typeof password === "string") {
    return toUtf8Bytes(password, "NFKC");
  }
  return getBytesCopy(password);
}

// node_modules/ethers/lib.esm/wallet/json-keystore.js
var defaultPath = "m/44'/60'/0'/0/0";
function getEncryptKdfParams(options) {
  const salt = options.salt != null ? getBytes(options.salt, "options.salt") : randomBytes3(32);
  let N2 = 1 << 17, r = 8, p = 1;
  if (options.scrypt) {
    if (options.scrypt.N) {
      N2 = options.scrypt.N;
    }
    if (options.scrypt.r) {
      r = options.scrypt.r;
    }
    if (options.scrypt.p) {
      p = options.scrypt.p;
    }
  }
  assertArgument(typeof N2 === "number" && N2 > 0 && Number.isSafeInteger(N2) && (BigInt(N2) & BigInt(N2 - 1)) === BigInt(0), "invalid scrypt N parameter", "options.N", N2);
  assertArgument(typeof r === "number" && r > 0 && Number.isSafeInteger(r), "invalid scrypt r parameter", "options.r", r);
  assertArgument(typeof p === "number" && p > 0 && Number.isSafeInteger(p), "invalid scrypt p parameter", "options.p", p);
  return { name: "scrypt", dkLen: 32, salt, N: N2, r, p };
}
function _encryptKeystore(key, kdf, account, options) {
  const privateKey = getBytes(account.privateKey, "privateKey");
  const iv = options.iv != null ? getBytes(options.iv, "options.iv") : randomBytes3(16);
  assertArgument(iv.length === 16, "invalid options.iv length", "options.iv", options.iv);
  const uuidRandom = options.uuid != null ? getBytes(options.uuid, "options.uuid") : randomBytes3(16);
  assertArgument(uuidRandom.length === 16, "invalid options.uuid length", "options.uuid", options.iv);
  const derivedKey = key.slice(0, 16);
  const macPrefix = key.slice(16, 32);
  const aesCtr = new CTR(derivedKey, iv);
  const ciphertext = getBytes(aesCtr.encrypt(privateKey));
  const mac = keccak256(concat([macPrefix, ciphertext]));
  const data4 = {
    address: account.address.substring(2).toLowerCase(),
    id: uuidV4(uuidRandom),
    version: 3,
    Crypto: {
      cipher: "aes-128-ctr",
      cipherparams: {
        iv: hexlify(iv).substring(2)
      },
      ciphertext: hexlify(ciphertext).substring(2),
      kdf: "scrypt",
      kdfparams: {
        salt: hexlify(kdf.salt).substring(2),
        n: kdf.N,
        dklen: 32,
        p: kdf.p,
        r: kdf.r
      },
      mac: mac.substring(2)
    }
  };
  if (account.mnemonic) {
    const client = options.client != null ? options.client : `ethers/${version}`;
    const path = account.mnemonic.path || defaultPath;
    const locale = account.mnemonic.locale || "en";
    const mnemonicKey = key.slice(32, 64);
    const entropy = getBytes(account.mnemonic.entropy, "account.mnemonic.entropy");
    const mnemonicIv = randomBytes3(16);
    const mnemonicAesCtr = new CTR(mnemonicKey, mnemonicIv);
    const mnemonicCiphertext = getBytes(mnemonicAesCtr.encrypt(entropy));
    const now = /* @__PURE__ */ new Date();
    const timestamp = now.getUTCFullYear() + "-" + zpad(now.getUTCMonth() + 1, 2) + "-" + zpad(now.getUTCDate(), 2) + "T" + zpad(now.getUTCHours(), 2) + "-" + zpad(now.getUTCMinutes(), 2) + "-" + zpad(now.getUTCSeconds(), 2) + ".0Z";
    const gethFilename = "UTC--" + timestamp + "--" + data4.address;
    data4["x-ethers"] = {
      client,
      gethFilename,
      path,
      locale,
      mnemonicCounter: hexlify(mnemonicIv).substring(2),
      mnemonicCiphertext: hexlify(mnemonicCiphertext).substring(2),
      version: "0.1"
    };
  }
  return JSON.stringify(data4);
}
function encryptKeystoreJsonSync(account, password, options) {
  if (options == null) {
    options = {};
  }
  const passwordBytes = getPassword(password);
  const kdf = getEncryptKdfParams(options);
  const key = scryptSync(passwordBytes, kdf.salt, kdf.N, kdf.r, kdf.p, 64);
  return _encryptKeystore(getBytes(key), kdf, account, options);
}
async function encryptKeystoreJson(account, password, options) {
  if (options == null) {
    options = {};
  }
  const passwordBytes = getPassword(password);
  const kdf = getEncryptKdfParams(options);
  const key = await scrypt2(passwordBytes, kdf.salt, kdf.N, kdf.r, kdf.p, 64, options.progressCallback);
  return _encryptKeystore(getBytes(key), kdf, account, options);
}

// node_modules/ethers/lib.esm/wallet/hdwallet.js
var defaultPath2 = "m/44'/60'/0'/0/0";
var MasterSecret = new Uint8Array([66, 105, 116, 99, 111, 105, 110, 32, 115, 101, 101, 100]);
var HardenedBit = 2147483648;
var N = BigInt("0xfffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141");
var Nibbles2 = "0123456789abcdef";
function zpad2(value, length) {
  let result = "";
  while (value) {
    result = Nibbles2[value % 16] + result;
    value = Math.trunc(value / 16);
  }
  while (result.length < length * 2) {
    result = "0" + result;
  }
  return "0x" + result;
}
function encodeBase58Check(_value) {
  const value = getBytes(_value);
  const check = dataSlice(sha2562(sha2562(value)), 0, 4);
  const bytes2 = concat([value, check]);
  return encodeBase58(bytes2);
}
var _guard3 = {};
function ser_I(index, chainCode, publicKey, privateKey) {
  const data4 = new Uint8Array(37);
  if (index & HardenedBit) {
    assert(privateKey != null, "cannot derive child of neutered node", "UNSUPPORTED_OPERATION", {
      operation: "deriveChild"
    });
    data4.set(getBytes(privateKey), 1);
  } else {
    data4.set(getBytes(publicKey));
  }
  for (let i = 24; i >= 0; i -= 8) {
    data4[33 + (i >> 3)] = index >> 24 - i & 255;
  }
  const I = getBytes(computeHmac("sha512", chainCode, data4));
  return { IL: I.slice(0, 32), IR: I.slice(32) };
}
function derivePath(node, path) {
  const components = path.split("/");
  assertArgument(components.length > 0, "invalid path", "path", path);
  if (components[0] === "m") {
    assertArgument(node.depth === 0, `cannot derive root path (i.e. path starting with "m/") for a node at non-zero depth ${node.depth}`, "path", path);
    components.shift();
  }
  let result = node;
  for (let i = 0; i < components.length; i++) {
    const component = components[i];
    if (component.match(/^[0-9]+'$/)) {
      const index = parseInt(component.substring(0, component.length - 1));
      assertArgument(index < HardenedBit, "invalid path index", `path[${i}]`, component);
      result = result.deriveChild(HardenedBit + index);
    } else if (component.match(/^[0-9]+$/)) {
      const index = parseInt(component);
      assertArgument(index < HardenedBit, "invalid path index", `path[${i}]`, component);
      result = result.deriveChild(index);
    } else {
      assertArgument(false, "invalid path component", `path[${i}]`, component);
    }
  }
  return result;
}
var HDNodeWallet = class _HDNodeWallet extends BaseWallet {
  /**
   *  The compressed public key.
   */
  publicKey;
  /**
   *  The fingerprint.
   *
   *  A fingerprint allows quick qay to detect parent and child nodes,
   *  but developers should be prepared to deal with collisions as it
   *  is only 4 bytes.
   */
  fingerprint;
  /**
   *  The parent fingerprint.
   */
  parentFingerprint;
  /**
   *  The mnemonic used to create this HD Node, if available.
   *
   *  Sources such as extended keys do not encode the mnemonic, in
   *  which case this will be ``null``.
   */
  mnemonic;
  /**
   *  The chaincode, which is effectively a public key used
   *  to derive children.
   */
  chainCode;
  /**
   *  The derivation path of this wallet.
   *
   *  Since extended keys do not provide full path details, this
   *  may be ``null``, if instantiated from a source that does not
   *  encode it.
   */
  path;
  /**
   *  The child index of this wallet. Values over ``2 *\* 31`` indicate
   *  the node is hardened.
   */
  index;
  /**
   *  The depth of this wallet, which is the number of components
   *  in its path.
   */
  depth;
  /**
   *  @private
   */
  constructor(guard, signingKey, parentFingerprint, chainCode, path, index, depth, mnemonic, provider) {
    super(signingKey, provider);
    assertPrivate(guard, _guard3, "HDNodeWallet");
    defineProperties(this, { publicKey: signingKey.compressedPublicKey });
    const fingerprint = dataSlice(ripemd1602(sha2562(this.publicKey)), 0, 4);
    defineProperties(this, {
      parentFingerprint,
      fingerprint,
      chainCode,
      path,
      index,
      depth
    });
    defineProperties(this, { mnemonic });
  }
  connect(provider) {
    return new _HDNodeWallet(_guard3, this.signingKey, this.parentFingerprint, this.chainCode, this.path, this.index, this.depth, this.mnemonic, provider);
  }
  #account() {
    const account = { address: this.address, privateKey: this.privateKey };
    const m = this.mnemonic;
    if (this.path && m && m.wordlist.locale === "en" && m.password === "") {
      account.mnemonic = {
        path: this.path,
        locale: "en",
        entropy: m.entropy
      };
    }
    return account;
  }
  /**
   *  Resolves to a [JSON Keystore Wallet](json-wallets) encrypted with
   *  %%password%%.
   *
   *  If %%progressCallback%% is specified, it will receive periodic
   *  updates as the encryption process progreses.
   */
  async encrypt(password, progressCallback) {
    return await encryptKeystoreJson(this.#account(), password, { progressCallback });
  }
  /**
   *  Returns a [JSON Keystore Wallet](json-wallets) encryped with
   *  %%password%%.
   *
   *  It is preferred to use the [async version](encrypt) instead,
   *  which allows a [[ProgressCallback]] to keep the user informed.
   *
   *  This method will block the event loop (freezing all UI) until
   *  it is complete, which may be a non-trivial duration.
   */
  encryptSync(password) {
    return encryptKeystoreJsonSync(this.#account(), password);
  }
  /**
   *  The extended key.
   *
   *  This key will begin with the prefix ``xpriv`` and can be used to
   *  reconstruct this HD Node to derive its children.
   */
  get extendedKey() {
    assert(this.depth < 256, "Depth too deep", "UNSUPPORTED_OPERATION", { operation: "extendedKey" });
    return encodeBase58Check(concat([
      "0x0488ADE4",
      zpad2(this.depth, 1),
      this.parentFingerprint,
      zpad2(this.index, 4),
      this.chainCode,
      concat(["0x00", this.privateKey])
    ]));
  }
  /**
   *  Returns true if this wallet has a path, providing a Type Guard
   *  that the path is non-null.
   */
  hasPath() {
    return this.path != null;
  }
  /**
   *  Returns a neutered HD Node, which removes the private details
   *  of an HD Node.
   *
   *  A neutered node has no private key, but can be used to derive
   *  child addresses and other public data about the HD Node.
   */
  neuter() {
    return new HDNodeVoidWallet(_guard3, this.address, this.publicKey, this.parentFingerprint, this.chainCode, this.path, this.index, this.depth, this.provider);
  }
  /**
   *  Return the child for %%index%%.
   */
  deriveChild(_index) {
    const index = getNumber(_index, "index");
    assertArgument(index <= 4294967295, "invalid index", "index", index);
    let path = this.path;
    if (path) {
      path += "/" + (index & ~HardenedBit);
      if (index & HardenedBit) {
        path += "'";
      }
    }
    const { IR, IL } = ser_I(index, this.chainCode, this.publicKey, this.privateKey);
    const ki = new SigningKey(toBeHex((toBigInt(IL) + BigInt(this.privateKey)) % N, 32));
    return new _HDNodeWallet(_guard3, ki, this.fingerprint, hexlify(IR), path, index, this.depth + 1, this.mnemonic, this.provider);
  }
  /**
   *  Return the HDNode for %%path%% from this node.
   */
  derivePath(path) {
    return derivePath(this, path);
  }
  static #fromSeed(_seed, mnemonic) {
    assertArgument(isBytesLike(_seed), "invalid seed", "seed", "[REDACTED]");
    const seed = getBytes(_seed, "seed");
    assertArgument(seed.length >= 16 && seed.length <= 64, "invalid seed", "seed", "[REDACTED]");
    const I = getBytes(computeHmac("sha512", MasterSecret, seed));
    const signingKey = new SigningKey(hexlify(I.slice(0, 32)));
    return new _HDNodeWallet(_guard3, signingKey, "0x00000000", hexlify(I.slice(32)), "m", 0, 0, mnemonic, null);
  }
  /**
   *  Creates a new HD Node from %%extendedKey%%.
   *
   *  If the %%extendedKey%% will either have a prefix or ``xpub`` or
   *  ``xpriv``, returning a neutered HD Node ([[HDNodeVoidWallet]])
   *  or full HD Node ([[HDNodeWallet) respectively.
   */
  static fromExtendedKey(extendedKey) {
    const bytes2 = toBeArray(decodeBase58(extendedKey));
    assertArgument(bytes2.length === 82 || encodeBase58Check(bytes2.slice(0, 78)) === extendedKey, "invalid extended key", "extendedKey", "[ REDACTED ]");
    const depth = bytes2[4];
    const parentFingerprint = hexlify(bytes2.slice(5, 9));
    const index = parseInt(hexlify(bytes2.slice(9, 13)).substring(2), 16);
    const chainCode = hexlify(bytes2.slice(13, 45));
    const key = bytes2.slice(45, 78);
    switch (hexlify(bytes2.slice(0, 4))) {
      case "0x0488b21e":
      case "0x043587cf": {
        const publicKey = hexlify(key);
        return new HDNodeVoidWallet(_guard3, computeAddress(publicKey), publicKey, parentFingerprint, chainCode, null, index, depth, null);
      }
      case "0x0488ade4":
      case "0x04358394 ":
        if (key[0] !== 0) {
          break;
        }
        return new _HDNodeWallet(_guard3, new SigningKey(key.slice(1)), parentFingerprint, chainCode, null, index, depth, null, null);
    }
    assertArgument(false, "invalid extended key prefix", "extendedKey", "[ REDACTED ]");
  }
  /**
   *  Creates a new random HDNode.
   */
  static createRandom(password, path, wordlist10) {
    if (password == null) {
      password = "";
    }
    if (path == null) {
      path = defaultPath2;
    }
    if (wordlist10 == null) {
      wordlist10 = LangEn.wordlist();
    }
    const mnemonic = Mnemonic.fromEntropy(randomBytes3(16), password, wordlist10);
    return _HDNodeWallet.#fromSeed(mnemonic.computeSeed(), mnemonic).derivePath(path);
  }
  /**
   *  Create an HD Node from %%mnemonic%%.
   */
  static fromMnemonic(mnemonic, path) {
    if (!path) {
      path = defaultPath2;
    }
    return _HDNodeWallet.#fromSeed(mnemonic.computeSeed(), mnemonic).derivePath(path);
  }
  /**
   *  Creates an HD Node from a mnemonic %%phrase%%.
   */
  static fromPhrase(phrase, password, path, wordlist10) {
    if (password == null) {
      password = "";
    }
    if (path == null) {
      path = defaultPath2;
    }
    if (wordlist10 == null) {
      wordlist10 = LangEn.wordlist();
    }
    const mnemonic = Mnemonic.fromPhrase(phrase, password, wordlist10);
    return _HDNodeWallet.#fromSeed(mnemonic.computeSeed(), mnemonic).derivePath(path);
  }
  /**
   *  Creates an HD Node from a %%seed%%.
   */
  static fromSeed(seed) {
    return _HDNodeWallet.#fromSeed(seed, null);
  }
};
var HDNodeVoidWallet = class _HDNodeVoidWallet extends VoidSigner {
  /**
   *  The compressed public key.
   */
  publicKey;
  /**
   *  The fingerprint.
   *
   *  A fingerprint allows quick qay to detect parent and child nodes,
   *  but developers should be prepared to deal with collisions as it
   *  is only 4 bytes.
   */
  fingerprint;
  /**
   *  The parent node fingerprint.
   */
  parentFingerprint;
  /**
   *  The chaincode, which is effectively a public key used
   *  to derive children.
   */
  chainCode;
  /**
   *  The derivation path of this wallet.
   *
   *  Since extended keys do not provider full path details, this
   *  may be ``null``, if instantiated from a source that does not
   *  enocde it.
   */
  path;
  /**
   *  The child index of this wallet. Values over ``2 *\* 31`` indicate
   *  the node is hardened.
   */
  index;
  /**
   *  The depth of this wallet, which is the number of components
   *  in its path.
   */
  depth;
  /**
   *  @private
   */
  constructor(guard, address, publicKey, parentFingerprint, chainCode, path, index, depth, provider) {
    super(address, provider);
    assertPrivate(guard, _guard3, "HDNodeVoidWallet");
    defineProperties(this, { publicKey });
    const fingerprint = dataSlice(ripemd1602(sha2562(publicKey)), 0, 4);
    defineProperties(this, {
      publicKey,
      fingerprint,
      parentFingerprint,
      chainCode,
      path,
      index,
      depth
    });
  }
  connect(provider) {
    return new _HDNodeVoidWallet(_guard3, this.address, this.publicKey, this.parentFingerprint, this.chainCode, this.path, this.index, this.depth, provider);
  }
  /**
   *  The extended key.
   *
   *  This key will begin with the prefix ``xpub`` and can be used to
   *  reconstruct this neutered key to derive its children addresses.
   */
  get extendedKey() {
    assert(this.depth < 256, "Depth too deep", "UNSUPPORTED_OPERATION", { operation: "extendedKey" });
    return encodeBase58Check(concat([
      "0x0488B21E",
      zpad2(this.depth, 1),
      this.parentFingerprint,
      zpad2(this.index, 4),
      this.chainCode,
      this.publicKey
    ]));
  }
  /**
   *  Returns true if this wallet has a path, providing a Type Guard
   *  that the path is non-null.
   */
  hasPath() {
    return this.path != null;
  }
  /**
   *  Return the child for %%index%%.
   */
  deriveChild(_index) {
    const index = getNumber(_index, "index");
    assertArgument(index <= 4294967295, "invalid index", "index", index);
    let path = this.path;
    if (path) {
      path += "/" + (index & ~HardenedBit);
      if (index & HardenedBit) {
        path += "'";
      }
    }
    const { IR, IL } = ser_I(index, this.chainCode, this.publicKey, null);
    const Ki = SigningKey.addPoints(IL, this.publicKey, true);
    const address = computeAddress(Ki);
    return new _HDNodeVoidWallet(_guard3, address, Ki, this.fingerprint, hexlify(IR), path, index, this.depth + 1, this.provider);
  }
  /**
   *  Return the signer for %%path%% from this node.
   */
  derivePath(path) {
    return derivePath(this, path);
  }
};

// node_modules/ethers/lib.esm/wordlists/bit-reader.js
var Base64 = ")!@#$%^&*(ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz-_";
function decodeBits(width, data4) {
  const maxValue2 = (1 << width) - 1;
  const result = [];
  let accum = 0, bits = 0, flood = 0;
  for (let i = 0; i < data4.length; i++) {
    accum = accum << 6 | Base64.indexOf(data4[i]);
    bits += 6;
    while (bits >= width) {
      const value = accum >> bits - width;
      accum &= (1 << bits - width) - 1;
      bits -= width;
      if (value === 0) {
        flood += maxValue2;
      } else {
        result.push(value + flood);
        flood = 0;
      }
    }
  }
  return result;
}

// node_modules/ethers/lib.esm/wordlists/decode-owla.js
function decodeOwlA(data4, accents3) {
  let words7 = decodeOwl(data4).join(",");
  accents3.split(/,/g).forEach((accent) => {
    const match = accent.match(/^([a-z]*)([0-9]+)([0-9])(.*)$/);
    assertArgument(match !== null, "internal error parsing accents", "accents", accents3);
    let posOffset = 0;
    const positions = decodeBits(parseInt(match[3]), match[4]);
    const charCode = parseInt(match[2]);
    const regex = new RegExp(`([${match[1]}])`, "g");
    words7 = words7.replace(regex, (all, letter) => {
      const rem = --positions[posOffset];
      if (rem === 0) {
        letter = String.fromCharCode(letter.charCodeAt(0), charCode);
        posOffset++;
      }
      return letter;
    });
  });
  return words7.split(",");
}

// node_modules/ethers/lib.esm/wordlists/wordlist-owla.js
var WordlistOwlA = class extends WordlistOwl {
  #accent;
  /**
   *  Creates a new Wordlist for %%locale%% using the OWLA %%data%%
   *  and %%accent%% data and validated against the %%checksum%%.
   */
  constructor(locale, data4, accent, checksum7) {
    super(locale, data4, checksum7);
    this.#accent = accent;
  }
  /**
   *  The OWLA-encoded accent data.
   */
  get _accent() {
    return this.#accent;
  }
  /**
   *  Decode all the words for the wordlist.
   */
  _decodeWords() {
    return decodeOwlA(this._data, this._accent);
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-cz.js
var words2 = "0itatkastcenaovo$taouleraeki&chor*teci%enbalodaeladet'!Chn=0Di#%E%^1Resa2Rese3CeT'#0EjKohol0Pu)%0A&sDul#Ekdo)Ke)Ti#Ul|3}aOgan%0FaltI$@tPi,%TmaTronom0LasL{i#Ol0Tobus4Yl:B#}<CilCul#D!_Ge)GrHnoKterieLa^L{#LkonLon-LvanLzaMbusNkom!R[rR{RmanRokoRvaTer#TohVl&Zal#Zili#Zu#3D&RanSe^StieTonZin#ZmocZ)k3CyklD]Ft-KinyLan%Og,fO]gTvaZon2AhobytAt*/E/aEdu+EskIk!Iz&Ok|Oud Ud2B-BrDl.D~H!(JkotJ|K<ysLe$R>R'?TaUb_U/!U^U+Ur!Xer2A^v#Ambo,An#AtrEp)Ike)KoLohOnzOskevUn{#Usin#Z^Zy2Bl.Bn|})D _D#D'aF{Jar(Kv?LdokLvaN^NkrRzaTikVolZola3D+tL.T'#0Ukot:PartRev&3DrDu+J/JnLaLerLkemLn?N.Nn(N'#NtrumNz<StopisT#2AlupaAp`]Ar aA)E/t!EmieI/otIrurgL`Le[Lub M_Mu,ObotO/olOd[O+,Om=Op Oro[OvRapotRl RtRupTiv(Ud.Utn!V!Vil#V(Y[Y$!Yt 0Bu+Gare)H_&HlaNkotRkusSter&Ta%TrusZin>Z(2O&2KolivUv!4It_N(0Dn(Ke)KrPot0Ak~AlIkRkot2Kli$a:L-oRe[T_Tum1E,1B!a}'#Cib_Fic Fla%KlKr{Mokr!PreseRbyS#T-tiv3Kob,zKt|O^P]mSkSp+jV`]Vo/2AhaOuhoUhopis1Es0BroByt-C@t}ut DnesH+dHo^H,JemJn?Kl`KolaKt<Kum@tLarLevaL.MaM.ntMluv M'Nut P`PisPln PosudPr'odPu$ Raz R(RtSahSl'St!-SudSy)TazT-Tk~Uf!Utn!Voz%Z`uZn!Z<%2Aho)AkAm!ikAv>AzeDolObn(OgerieOzdSn(T Z(2B@}'noD-HaH'#S SnoT(0Oj?Or>2Nam :9O]gOnomie0EktronIpsa0AilIseO%P!ie2Izo^O/aOpejOs2EjEn%K<)Kymo0Ike)0F<ie0Olu%1Eku%KurzePed?P]zeP<tT,kt:C#Jf#Kul)N!ikN)zieRmacieV< Zo+3De,%J{onN#3Al#Gu,ntLozofLtrNan%N)Xa%0Ord1An_IrtOtila2NdSf<T[lT#Ton2Ak%Es#On)2KarNk%3Zi#:LejeRant3N{i#O]g3Lot.2Azu,Ejt2LemLfi$aTi#2AfAmofonAnu+EpIlOgOtes#2Ma:D?DrLaL@#N[NopisRfaRpu&V,n3Bk(J#lJnoJtmanK)rLmaM!omR>R&S]Zky3St<ik2Ad'#AsivkyAvaEd!EnO^v>OhOup(T!Ub.U/o)0AtO)Yz0IsOjivoOut0Bl.Boj}DinyDl!Dno)D|Jn(KejLin#L#LubMo+N [No,%RalR^RizontRkoRliv>RmonRn.RoskopR$voSpo^St.T'(U[UfUp!Us#V<2Ad[An?Av(Az^Bo+kD.D]D(N-Ob#Oma^OtOu^Oz@St#Ub(Yz!2B@(B~D[KotMrS aSto)0Ozd2Bn(D,ntGie&M&Sterik:2Yl#3Ned2O&0Uze0Un a0F-%Fla%KasoOva%Sp-%Tern{Vali^Ve$<Zer%3Onie:Blko})Ho^Kmi+K(L'>N)rRmarkRoSanSnoT#V<Zyk3Din>D+Dn!_HlanKotL@L oMn(NomP?S{erV Zd>Zero3NakNdyNo/Sk,Sto)Trn?Zva3En|1Gurt5R):Bar{B_Bin{}&D{Did]HanJakJu)KaoKtusLam aLhotyLibrLn(Me,MkolivM&Ni[lNoeNt<Pal.P_aP olaP#P+Po)PrPu$aPy[,Ram_Rot#RtonSaTa]gTed,U%UzaVa+cZaj#Ze)Ziv(2EkolivEsi0Dlub@MpRami#3No2A%kAdivoAmApotAsi#AunEcEn[Ep!Es~IdImaIs&Ob*kO#nOpaOubUb'&Us!Uzk(0EnIt!Otr0IhaOt0Al?Ber>B#BlihaBylaC*rH=J@>KosKtejlLapsLe^LizeLoMandoMe)MikMn!aMo,MpasMun aN!N%ptNd?N>NfeseNgresN.NkursN)ktNzervaPan>PieP~Pr'#Rb_R-t<Rmid]RoptevRpusRu&RytoRz{S!>St#T_T+)T*lUk!Up_&Us-Uz]VbojZaZ<oh2Ab?A/Aj.Al|AsopisAv!aEd EjcarEs[Eve)Ik{ItikIzeKav>Me+cMivoOcanOkOni#Op OupaOv#T-Uh`]Up?Ut(Vin#Y/+Yp)Y$alYt2Dlan#FrJn(KlaLaj^Li/L#Lom{Ltu,NaPodivuRtRz<Til0Al aAsin#E$<2No]gS_.Ta,T?T#T'>V`]:B,d<})nDn(IkKom>M_aMpaN'#S?SoStu,Tin#V.3B#CkdyD@Dn?D'#Dv.G@^GieG,%H%Hk(H~KtvarNo/odNtil#P@#Pid]T`]T>TmoTokruhVhartV a%Vobok3B,}ot#DojedDsk(H'.Jav>L-M{#NieN#No+umStop`T.T|5Bi$aDivodGi#GopedKal aK{Mc|P!aPu/RdSosTrU^lUhU#Usk!V>3Tiv(1Cer&CiferMpSkSt,%0I%2RaRi#S.:DamD]Gi$rHagonJ{-J _J< aKakK'?Kr_aL[L.L|Lv?Min#Nd+NkoRn(SakrSkotSopu$T?Tri#Tur aZan>ZivoZl Zur#2Lo[0}anikD a%D'.LasaL*nNtol#TlaTo^TrZe,3G,%H~Hu+K.KrofonL@>Lim{rL(Mi#Nc'&Ni[rNom{Nul(S#StrX|2Ad(HaH'.OkS!Uv 1I/Ohem0BilCn(D_#Dl [HylaKroL-ulaM@t#Nar/aNoklN$rumNt|NzunSazSkytStTiva%T<#Ty#U/aUdr(Zai#Z-Zol2Am<Av@>KevTvolaZ{Zut(0T _1DrcF]nL!MieN?S{Ta%ZeumZi#nt3Sliv>0Da:B*r!}yt!Da%Dbyt-DhozDobroDpisHlasHn!Hodi+H,d Iv aJedn*Ji$oJm=K]n Kon>Krm LevoMaz!Mluv Nom{rOkoOpakO$roP`!PevnoPln P~Pos+dPr(oRod RubyRy/]S` S-!S+poSt!TolikV@-Vr/Vzd<yZv!3Be}!CkyDa+koDb!DuhGa%H{Ho^J@JprveKlidLib(Mil(MocO/o)On#PokojR(RvSmyslS*l`Tv<UronV.Zvyk+3Co)JakKamKdyKlKte,kTro5C+hHav?M.%RaR-S _Sn(UzeVinyVo)Zd,5DaD+G{T Tn(Trie3Mfa:0AlArv AvaDivEcEhn!Ejm=Ez aHajo[Iln?Jasn J-tK]p La$L-Li[LohaLu^NosOh! Oj-OutRaz>R&Ru[RysSahSluhaS)r!UvVazVin VodVyk+Yv!_Z<0AsElEn Hl` Ho)H,&It~0BojByt}odCiz Ebr!Esl!Evzd!EzvaH`%Hod J{JinudKazK*p LivLu#Ml#Oln(P`PisPl=P<Pu$ Pyk!Raz#S*d StupSunTokTudVahaVe)Vol!V,%tZ&k1I&Sajd1LasNiskoRa^Roz Ryz-2ApEn?Li#NoOuzl OvyRasaResRs-RuhUpantUr#Us 0Ejn.Iz|0AkE+)Ez L`.L*v!LuvaYl0Ehdy1Ak|As-E,%I%Il(Is|O,Oz?RavduRoti1B al}e$rGieL?LojT_0A^}~I#IvoLavaLep Ln L' N'aO[Ol Pa+cT@T,haTu^Ty/Voj 0Epl IskOpRh!Rl(RokRubyV<1A~ArEsLivn O%1Id1Do[:}!_Ci@tD*/H<-KtLan^L>LivoLu[Mf+tMls-N@#Ni#N&N|N$voNtof+Pri#Rke)RodieR)Ru#Ry[Se#Siv aSt_#T@tTro&V*kZnehtZ*r-3C#DagogJs-K]LotonNal)Ndr-NzeRiskopRoStr(Tar^T?Tro+jVn.Xeso3Ani$aHaJav?K+KnikL.Ln(Lul#Nze)Pe)S!_Sto+Tev&Vn?V'ar2A%n)Ak!Am@Ane)A$i#At Avid]AzE/Em@oEn)EsEtivoEv_Iv!N NoO/aOd.Om[OutUkYn2Bav Byt}odC Ctiv>D!D%n Deps!Dh+dDiv Dkl`Dman DnikDo[Dpo,D,zD$!aDvodDzimEzieHan#Hnut#H'<HromaHybIn)Ji$#Jm=Kaz K+sKojKrokKu)KynLedneLib-Lk~LohaLynomMaluMi~Ml#MocM$aMys+tNe/!N<#Nur(P`!P_Pis-Pla/Pros Ps!PudR`%R%RodRu/aRyvS` SedSilaSkokSlan>S*d SpoluS)vaSud-SypTahT#nT+skTom-T,vaTupaTvo,U#zUtoUzdroVahaVidlaVlakVozVr/V$!VykVzde/Zd,vZem-Zn!-Z<Zv!2Ac|Ah<yAkti#A+sAot>Ap<-AseAv^IncipKnoObud O%ntoOdejOfeseOh,Oj-tO]m Omi+Onik!Op`OrokOs[OtonOut-OvazS#v#St@Udk(UtV-Voh<y0An>OvodTruh0Actvo0Ber)}DlKav>Kl.Kr+LtMpaNcP@SaSin#St.T|Ty#3Rami^SkT_::C-}otDia%Dn?DonFtGbyKe)K'.M@oMp*/NdeRa/R aS'&StrTo+$Zan%Zid]3Ag|Ak%CeptDakt<Fer@tF+xJnokKlamaK<dKrutKt<Pu)%VizeVmaVolverZerva3Sk|Ziko5Boti#Dokm@H'#K+KokoMan{oP'odPu/aRejsSolStl.Tmi$rTopedTun^Ub@#U/oUpU,V.Vn?Zb<Z/odZd!Zezn!Zhod%Zin#ZjezdZ#zZ]haZmarZp`Zru/ZsahZtokZumZvod5Bri#}`]Kav?Kopis3BaBol'}l(D]P`]T.Z(:Di$aH!KoM>Mizd!Mo)N #Rdin#San#T_ Z[Z@?0Or0H|1B,n#CeseD`]Dim@tD]Hn!Jm=Ke,K)Kun^KvojeM@oNoRvisS` Sho,SkokSl!St,SuvSyp!T[T.Tk!T~Trv!VerZ&m2O^R~0FonLn?R#Rot-RupTua%1AfandrAliskoAnz@AutEptikIcaL`[L@?LoLuzO[O#nOroRip)RzUp.V(Vr&0Abi#Adid]An.A$Avn(Ed|Ep>EvaEz.IbI&Izn?OnOup-OvoU/UhaUn%Up#Za0A,gdE)&Il$voL*vaOgR`RkRt#Ut-Ysl0AdAhaOb0Bo)}aD'#KolP#TvaUbojUc Ud%UhlasUl`Um,kUp,vaUsedUtokUvis{0Al'&As _IsLavOd-Oj@>OluOnz<Orn(Ou$aR/aU$ 1An^AzD%NaN>Ovn!P@StUb1An?Ar(aAti#Av[EhnoEz#OdolaO+kOpaOrnoOup!Ra/ResRh~RomRu&Ud&Upn?VolYk0Bj-tBtropy}arD(KnoNd!N=Rik!aR'.0AhAl$voEtrAt[Az-Is+It-Obo^Odid]Or#Rab2Kav#KotN-N'>P!Pk(R'(S_T(:B+t#Bu+H*nJemnoJfunJgaJ Jn(Kti#Mh+MponNc|N>NkerPe)V@.Z!_3}ni#HdyKut.LefonMno)Nd@%Ni$aN<P])P&PrveRapieRmos#Xtil3}oSkopisTu+k1Ad+cAn.0Ap#Esk!UkotUpa0El1A+)Pin#PolRzoUhaU+c2Ad?Akt<AmpAsaAverzaEf E$Ez<Hav.Hl.O/uOj?Os#Ou[P%P _Pk(Ub>U/l Uhl?UsV!2DyH~H(Nd,Ri$aR&jZemsko0ArohOr[Rd(Rz2GrKev:0Oh(OzeR!R*s-RusYt'&0HoTiv(0Iv 3R` 1Edn!I$ M=0Az!_Lidn Lon Otv Roj 0I%I)Ov 0Yv`]0Av If<maIk~1Ad~L!n Ly~Out!Rav 1AnAz 0Ed~Il|Mrt N`n N=Oud Tl!Tr~0Ah|K!Lum O~Op@>R*s 1Al Oln Oz'#3D,v ElEn.L.N!:GonL/aL*nNaN^lNil#RanRhanyR|1ElkuHod0Ova0DroGe)%J%Lbl*dL{rhL _LmocLry[Nk'Ran^RzeS_#SkrzeSn?SpoduS)Ter.Ver#3B,%}rDeoh,D.D+LaN?S{Tal aZeZ #0Ezd0L`Us0Aj#AkAs>EvoHk(IvN'#Oup!1Uc|Uk0DaDiv(Doz&kD$voJ@skyJ&JskoLantL[L LnoSk'#Zid]Z'&0Ravo1Ab>A%tAhA)Ba}o+kH!StvaTu+0Ad T*p Tup0Ip4Bav Br!}|D!D,Fot H+d!H~Hod H,d Hub Jasn J{Jm=K]p Kon!L-!Maz!Mez Miz{Mys+tNe/!Nik!Nut P`!Pl! P,v Pu$ Raz R'n!Rv!Sl' SokoS)v Su~Syp!Tas Tes!Tr! Vi~Vol!Vrh_Zdob Zn!0AduBud }op D<Du/Dy/!E$upH+demKazLyk!NikOr-P*,TahT-::993Lofon::Br!Byd+t}|DarmoDus F*k!Hlt Hod H,^Hy~J!>J{Ji$ K+p!K*p Lep Mez Mot!Mys+tNe/!Nik!Pl! Poj Ps!Raz S)v Su~Taj Temn Tk~Ujm=Val Ve+tVin Vol!Vrt!Zvon 0Av RusuUd|Yt-1A+#ArmaAtn(IvoOb RojVihYm`]0L@.ManM.Pt!Z`uZdola2At Lt~Lubo#Ot' Ru[0MaMn?0Emn 0Lam!Oum!R!#Umav#0AtoEh#O[OmO$Ozvyk0Ap|ArAt-IjeIz{Ocn Odr!Rzl.Ut|0AkAl(Am@!Ovu0B,z Tav Ub-Ufa+0Lod Omal RavaR( Rud#Rvu1A^An C`]N (NoOv&Y/l Zav(1I/aR! 0B'.Br0Ed~EnkuEs_aOnR!Uk'odYk";
var checksum2 = "0x25f44555f4af25b51a711136e1c7d6e50ce9f8917d39d6b1f076b2bb4d2fac1a";
var wordlist2 = null;
var LangCz = class _LangCz extends WordlistOwl {
  /**
   *  Creates a new instance of the Czech language Wordlist.
   *
   *  Using the constructor should be unnecessary, instead use the
   *  [[wordlist]] singleton method.
   *
   *  @_ignore:
   */
  constructor() {
    super("cz", words2, checksum2);
  }
  /**
   *  Returns a singleton instance of a ``LangCz``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist2 == null) {
      wordlist2 = new _LangCz();
    }
    return wordlist2;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-es.js
var words3 = "0arertoiotadonoaRteirroenaNonaLsolocoiliaralaorrenadaChoN$n0A>Dom,EjaI!#Oga&O'Or#RazoR*Ue=U<0Ab Adem@Ce<C~Ei)ElgaEn#Ept I&L  NeOg!O<TivoToTrizTu Ud*U!&Us 0Ic#Mit*Opt Or'Ua`Ul#0Reo0Ect Ic~In Irm 0IlIt On@Os#Ot Reg R$UaU&U?aUja0OgoOr+0ReSl 0Ed_zE'Us)1Ac[nAmb_ArmaBaBumCaldeDeaEg_Ej Er%E%F?!GaGo&nIa&I,#Iv$MaMejaMib T TezaTivoToTu[Um'Z 0AbleAn)Apo]ArgoAs B Bi#E'IgoIs%dOrP oPl$0|oC@'C]D D,Em@Gu=Il=ImoIsOt T,aTiguoTojoUalUl Unc$Ad*EjoO1Ag A[#Eti#IoLic O&Or)OyoR,d!Rob Ues%U+1A&A`ArBit+BolBus#|ivoCoD!D?]DuoEaI&IesM.i-esOmaPaP.Reg=RozRugaTeTis%0AA&Al#C,<Egur EoE<rI,#I=Ist*NoOmb+P!oT?]T+Tu#Um*Un#0AjoAqueArEn#EoI>Le%OmoRa!RozUn0DazD$GeLaM,#S,)T^0AlAnceA+EEl]`E`EstruzI.I<2ErU{U'0Af[nArO)Uc Uf_Ul:BaB^|eH@IleJ Lanz/c.LdeMbuN>Nd-oRb(>RnizR+Scu]S#nSu[Tal]T!@T*Tu%UlZ 3BeBid/=S SoSt@3|oEnNgo2An>OqueUsa2ABi`BoCaCi`DaDegaIn//!oLsaMb-{dNi#N}saiRdeRr SqueTeTinVe{Zal2AvoAzoEchaEveIl=In>IsaOcaOmaOnceO)UjaUs>U#2CeoCleE'EyFan{F.HoIt_L#Rbuj(l(+Sc TacaZ.:Bal=BezaBi`B[CaoDav!D,aErFeI{ImanJaJ.LLam Lc$L&Li{dLleLm/^LvoMaMb$Mel=Mi'Mp}c!Nd?Nel-gu+Nic-#N-.ObaOsPazPi%nPo)Pt Puch((b.RcelRe%Rg(i'RneRpe%R+R%SaS>S!oSpaS#rT^ceT_U{lUsaZo3Bol]D!D+Ld/eb_Lo<Lu]M,#Niz-t+Rc(&Rez(oRr R)zaSpedT+2AcalAle>AmpuAnc]ApaAr]I>Is)IvoOqueOzaUle%Up 0Cl.EgoE=EnEr#F[G +M->NeN%P_sR>Rue]SneTaU{d2Am^AnA+AseAveI,)ImaInica2B_Cc~|i'Ci`CoDigoDoF_G!He)JinJoL/ch/eg$Lg Lin/l LmoLum`Mba)M!Mi{Mo&Mpr-deNej}g-oc!Nsej}t PaPi(az.Rba%RchoR&nR.(r!S!SmosS%2AneoAt!E Ec!Ei&EmaIaIm,Ip%IsisOmoOnicaOque%U&Uz2Ad+Ar#At+BoBr*| aEl=En#Er{Es%EvaId Lebr/p/#Mb_Mpl*N-e%O%P.Pul( R$<R<RvaTis:M-z(R&T?3B!B?Ca{C*DoF,saFin*J LfinLga&Li#M^-<N%lP^)RechoR+%Sayu'SeoSf?eSnu&Sti'Sv$TalleT,!U{3AAb=AdemaAman)A`Ar$BujoCt En)E%EzFic?G'Lem/u*N!oRec#Rig*S>Se'Sf[zVaVi'5BleCeL^Ming}N Ra&Rm*R<SSis2Ag.Oga2|aDaE=E'LceOQueR Rez(o:0A'R$0H OUa&r0AdIc~Ific$I#rUc 1Ec#Icaz3EEmp=1Efan)Eg*Em,#Ev IpseI)Ix*Og$Ud*0Bu&It*Oc~Pa)Pe'PleoP_sa0A'C go|ufeC@EmigoE+Fa&F!moGa'Igm/aceOrmeRe&SayoS, T!oTr VaseV$1Oca0Uipo0Izo0Ca]C,aCol Crib*Cu&Enc@F!aFu!zoPa{PejoP@PosaPumaQuiT TeTi=Tufa0ApaEr'Ic-@1Ad*Alu En#It 1Ac#Am,Ce<CusaEn#Ig*Il$Ist*I#P!#Plic P.!T_mo:BricaBu]|a{C?C#rE`J/d/=L<Lt MaM?@Mo<Ra.Rmaci(olRsaSeTigaU`V^X3Br!o|/izORi(ozRt?Rv^Stin3AbleAnzaArB[Cc~|aDeoEb_ElE[Es%Gu[J JoL/e)L@lLt+NNc-g*Ni#Rma2A>Au%EchaOrO%U*UjoU^2B@CaGa%G.L$Lle#N&Rm(+Rtun(z SaTo2Aca<Ag?AnjaAseAudeE*E'EsaIoI#U%2EgoEn)ErzaGaM Nc~Nd(g.R@S?TbolTu+:Ce]FasI%JoL/!i/=Mb- Nch}g-<RajeRzaSoli`St ToV?an3Me=M*NN!}$N)Ran$R,)Rm,S#3Gan)M`s$R Ro2Aci OboOr@2LLfoLo<LpeM(&R?([TaTeoZ 2A{Afi>A'AsaAtisAveIe%Il=IpeIsI#O<rUaUe<UmoUpo2An)ApoArd@Er[IaI'I.I<It [Sa'St :B!B?Bl C!|aD/l Mac(i`ZZa`3B?]B[|oLa&L$Mbr(*Rma'RoeRv*3E=Er+Ga&Gi,eJoM'S#r@5Ci>G Gu!aJaMb_Ng}^Nr((mig('St?Yo5E>ElgaEr%E<EvoI{IrMa'Me&M?deM}d*RacanR#:1O'0EalIomaO=2Lesi/uUal4EgalUs~0Ag,AnIt P P!$P.!Pul<0CapazDiceEr)FielF^meG,$Ic$M,<MuneNa#Sec#S%n)T!esTimoTu*Ut?Vi!'3AIsOn@0L/o):BaliB.M.RabeRdinR[U]Zmin3FeRinga3Ne)5R`d(obaV,Ya5ErgaEvesEzGa&rGoGue)Ic$N>Ngl-$Nt Pit!R S#V,?Zg :7Lo5A]:B$C$C[DoD+nG #GrimaGu`I>M!Mi`Mp --ch-gos%NzaPizRgoRvaStimaTaTexT*U_lV Zo3AlCc~|eC#rErG~Gumb_Ja'Ngu-#NaOnOp &S~TalT[VeY,{3B!%dB+C^D!Di EnzoGaG!oMaMi)M.Mp$NceN&Ne-go)N}t!`Qui&SoS%T!aT$T+2AgaAmaAn#AveEg En Ev Or Ov!Uv@2BoC~CoCu[GicaG+MbrizM}jaTe5|aC*G J}-esPaSt+ToZ:Ce%|oD!aD_Du+Est+F@G@GoIzL{dLe%Ll/oMaMboMutN>N&Nej Ng-iquiNj N}<N%Na`PaQuin(R>Re(f?Rg,Ri&RmolR+nR)sRzoSaSc aSivoT T!@TizTrizXimoY^Z^ca3|aDal]D$Du]J?]J^L,/.M^i-^NsajeN)NuRca&R,gueRi#SS.TaT!To&T+Zc]3E&ElEmb+G/Lag+Lit Ll.M}-!}im}u#OpeR SaS!@S?SmoTadTo5|?aC~DaDe=HoJ LdeL!Li'M,#Mi- c-ed-j-#NoRad(d!Re'R*R+Rs(%lScaStr TivoV!V?Zo5|oD EbleE]Er)Est[G_J!L/e%L%N&Nec(alRoScu=SeoSgoSicaS=:C C~D IpeRanj(izRr SalTalTivoTu[lUseaValVeVi{d3C$Ct G Goc$G+OnRv$ToUt+V V!a3|oDoEb]E#NezNoTi&Vel5Bleza|eMin(i(m()TaTic@Va#Ve]V$5BeCaCleoD?=DoE[EveEzLoM!oTr@:Sis0E<IspoJe#R(!oS!v T,!V$0AA<Ea'H,%HoIoReTavoTub_Ul#Up Urr*0I IoIsea0S)0EnsaEr%Ic$Rec!0Ro1DoR0O1AEa{Fa#IvoLaMoOrVi&0Bligo0DaZa1A>C~E[In On!T TicaUes#1Ac~A&rAlBi%CaD,EjaGa'G@Gul=I,)Ig,Il]OQues%Uga0Ad@Cu+Ez'OT[0O'Ro1EjaU=1I&Ige'0En)0O':C#D_El]Gi`GoIsJ oLabr/>Le%Li&Lm/om/p NNalNi>Nt!-ue=PaPelP?]Que)R Rcel(edR*RoRpa&RqueR[foR)S SeoS~SoS%TaT$Tr@UsaU%VoYa<3A#nCa&C!a|oDalD*G IneL L{'Le/ig+LlejoLoLuc--s N.OnOrPi'Que'R(ch(d!Rez(f?Ri>Rl(mi<R+Rs.aSaScaSimoS%`Ta=T+leoZZu`3C |.EEd[Er`EzaJam/ Lo#Mi,%N}#rNz-aOjoP(a%S Sci`SoS%T.Zca2AcaAnA%AyaAzaEi#E'OmoUmaU[l2B_CoD!D$EmaEs@E%L,Lici/=LvoMa{Me=MoMp-!Rc~R%lSa{Se!SibleS)T,c@T+Zo2A&E>zEgun%Em$EnsaE<Ev$ImoIncipeIs~Iv OaOb Oce<Oduc#OezaOfe<rOg[maOleOmesaOn#Op$OximoUeba2Bli>|!oD^Eb=Er%Es#Lg/*Lm.LpoL<M-#NalNoPaP?(e:99Ed EjaEm Er!E<Ie#ImicaInceIt :Ba'B@BoC~DicalIzMaMp-ch}goPazPi&P#SgoSpaToYoZaZ.3Acc~Ali{dBa'Bo)Ca!Ce%|azoCog!C_oC#Cur<DD.&Duc*FlejoF^maF[nFug$Ga=G*G]G_<H,I'IrJ/a#LevoLieveLle'LojM Med$M}>rNd*N%P #Pet*Po<Pt?SSca)Si`Spe#S#Sum,T*oT^'T[#Un*VesVis%YZ 3CoEgoEn{EsgoFaGi&G^Nc.N.OQuezaSaTmoToZo5BleCeCi D DeoD?]ErJizoJoM!oMp!NN>N{PaP!oSaScaSt+T 5BiB^DoE{G*I&In/e%LoMboM^Ptu[TaTi`:Ba&B!B$BleC GazG[&L/&L!oL*Lm.L.Ls/#LudLv Mb-c~Ndi-e Ng_Ni{dN}#PoQueRdin()nSt_TanU`Xof.3Cc~CoC_#C%DGu*IsL=LvaMa`M?l-d-<rNalN^P  P@Qui(RieRm.Rv*S,%S~TaT,%V!oXoX#3D[Es%E)G=G'Lab/b L,c$L]Mbo=M$R,aS)maT$Tu 5B_C$D$LLap/{&Le{dLi&Lt Luc~Mbr-de}i&No+NrisaPaPl P^)R&Rp_s()oS)nTa'5AveB*Ce<D^Eg[E=E'Er)Fr*Je#L%nM P! Pl*P.!P_moRR>Re'Rg*S#T?:Ba>BiqueB]BuCoC#JoL L>L,#Ll/.Ma'Mb^Ng}quePaPe)P@P.Qu?l(deRe(if(je%RotR+R%TuajeU+ZaZ.3At+|oC]CnicaJa&J!Ji&L/efo'MaM^Mp=NazNd!N!NisN<Ori(api(>Rmi'Rnur(+rSisSo+StigoT!aX#Z3B$Bu+nEmpoEn{Er[E<G_J!/deMb_Mi&M}%OPi>PoR(.TanT!eTu=Za5Al]B?=C Ci'DoG/&M N}#P PeQueRaxR!oRm,%RneoRoRpe&R_R<RtugaSS>S!Xi>2AbajoAc#rA!Afi>AgoAjeAmoAnceA#AumaAz EbolEguaEin%EnEp EsIbuIgoIpaIs)IunfoOfeoOmpaOn>OpaO)OzoU>Ue'Ufa2B!@BoEr#MbaM^NelNic(bin(ismoR'T^:0Ic 9C!a0B[l0I{dIrIv!<OT A3Ba'BeG,)Na0ArU $0IlOp@1A:CaC$Cu`G GoI`J?l/eLi&LleL^Lvu]Mp*oR(i R.So3Ci'C#rHicu=In)JezL/!oLozN-c!Nd-e'Ng N*N%NusRRa'RboRdeRed(j(<Rt!3AAjeBr C$CtimaDaDeoDr$EjoErnesG^LLl-ag_N}e&OlinRalRgoRtudS^Sp!aS%Tami`U&VazV!oV*Vo5LcanLum,Lv!RazT ToZ5E=Lg :::C!Te3GuaM('So9DoGaGur:F*}jaPa#Rza93N(+5MoR&";
var accents = "aeiou7695@@BZWWavwUJkO@Y-Kn))YEGq#E@O)cI@#ZkMHv$e*))M!!)D**$GW!oKm*Acoh^k&It-pi^SYW)$^n!G)bO!Wkzam(jS#X)Og*^l^RW!bQ#QygBKXfzE))hti!Qm)Cng%%c)mJiI*HJWbmYniCLwNdYyY%WKO^bnT$PuGOr!IvHu&G(GKbtBuhiW&!eO@XMeoYQeCa#!MrTJCq!OW&CHG(WCcW%%)$rfrIegu$)w!G)JGmWWw)MnD%SXXWIT^LWAZuVWB^W)eTL^x&$WGHW(nKWEMA)#$F$x$Waekqs,n7715)W*HM-$WAcCiu(a))VCZ)GG%(*CWWdW%$D!UCO$M";
var checksum3 = "0xf74fb7092aeacdfbf8959557de22098da512207fb9f109cb526994938cf40300";
var wordlist3 = null;
var LangEs = class _LangEs extends WordlistOwlA {
  /**
   *  Creates a new instance of the Spanish language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langEs]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("es", words3, accents, checksum3);
  }
  /**
   *  Returns a singleton instance of a ``LangEs``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist3 == null) {
      wordlist3 = new _LangEs();
    }
    return wordlist3;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-fr.js
var words4 = "0erreleontiteurinueiriet cegeanseali medenel q)eniluxaus ch0Ais}And$Diqu E`#Ol*Ord Ou%rOy RasifReuv Ri,Rog RuptS_-SoluS'@UsifYss=0A@m+AjouAr+nCab]Cep,Clam Cola@Cro~eCu}ErbeHatHe,Idu]I Omp&Qu *R$y?Te'TifT)l0Ep&EquatHesifJec%fJug Mett!M* Op,Or Ouc*Res<RoitUl&V be0R R$ef0Fai!Fec,Fi~eF!{Fub]0Ac Enc I#I,Raf Reab#Ru?1D Gu`#L>Mab#S/-0Ou,Us,1Arm Chim+Er&Geb!G)I_ I?ntLeg Lia.Lou Lum O'd*PagaTes<Veo#0A&'BiguB!Enag Ertu?Id$Ir=Orc O'Ovib#Phib+P#'Us/t0Aly<Apho!Ar~+Atom+C+nE/%rG#Gois<Gu#{Im=Nex N$-N)lOd(Om=+Ony?Orm=T_[Tido&X+{1Ai}Eri%fL/*Olog+Pa!`Pe]Por,Puy 0UariumUeduc0Bit!Bus&De'Doi<G_tLequ(Matu!Me?ntMoi!Mu!P_,Ra~ Riv Ro}S_icT +lTic#0PectPh=&P* S|tS v*S+t&Soc>S' TicotT!Tu-0El>O?RiumRo-Ta^T_%fT* Trap 0Ba(eB .Da-Dib#G' Ro!Tom[Tru~e0A]Anc Ari-En*Er<Eug#Ia&'I@I$I}O(eOu R`1I=Io?:D.Fou Ga.G)t&Igna@L/c Lc$Le(eLisa.Mb(Ncai!Nda.Nl+)Nn>eNqu>Rb>R`R$R^Rra.Ss(S%$Ta`#Te|T,+Udr>Vard 3Let&L>Lo&Nefi-R-|Rg Rl(eRmudaSa-Sog[Ta`Ur!3B $Cyc#Du#JouL/L(g)LlardNai!Olog+Ops+OtypeScuitS$Sto'iTu?Zar!2AfardAg)An~*Ess/tInd OndOqu Ous$2BardB(eI!I}Li@Nb$Nd*Nhe'Nif>NusRdu!R[T&Uc#Ue{Ug+Ul$Uqu(Ur<Usso#U%^Xe'2An~eAs>AveEbisE~eEuva.Ico]Iga@Ill/tIo~eI^O~u!Od Onz Ous<Oye'U?Us^Ut=Uy/t2Ff#Iss$L#%nRe|R(S% T( To*Vab#Vet&:B/$B(eChet&De|D!Fe(eIllouIss$Lcu]Lep(Lib!Lm Lomn+Lvai!Mara@M aMi$Mpag[N=Net$N$N%[NularPab#Por=Pri-Psu#P,Pu~eRab(eRb$eRes}RibouRna.Rot&R!|Rt$Sca@S>S^Ssu!U}U%$V=>V [Viar3D`#Intu!Les&Llu#Ndr>Ns' Ntr=Rc#Rebr=Ri<Rn Rve|S}2Agr(Ai<A#'Amb!An-Apit!Arb$As<'At$Auss$Av* Emi<En`#Equ>Er~ Ev=I_Iff!Ign$Im eIotLoru!OcolatOis*O<O)t&Ro?U&0Ga!Gog[M_,NemaNtr Rcu]R R^T [Toy_Tr$V`2A*$A?'Aqu As<Av>I_tIgn ImatIva.O~eOna.Opor&2B=tBraCas<Co% D Dif>Ff!Gn Hesi$Iff Inc L eLibriLl(eLma,L$elMbatMed+Mm/@MpactNc tNdui!Nf>N.]Nno,Ns$[NtactNvexePa(P+Ra`Rbe|Rda.Rni~eRpusR!ctR&.Smi^Stu?T$U@Upu!Ura.U&|Uvr*Yo&2AbeA(&Ava&Ay$Eatu!Edi,E?{Eu}Evet&Ib]I Ist=I,eOi!Oqu Ota#Uci=UelYp,2Bi^E`l*Il]eIs(eIv!Lm( L%v Mu]Pi@Ra%fR<'3Anu!C#L(d!Ni^:Ign M>Ng N<'Uph(3Batt!Bi,Bord Brid But/tC= Cemb!Ch* Cid Clar Cor Cri!Cup]Da#Duc%fEs<F_sifFi]Fray Gag Givr Glu%rGraf Jeun Li-Log M/d Me' Mol*Ni~ Nou N&l#Nud PartP_}Pha}Plac Po}R/g Rob Sast!S-n&S tSign Sobe*Ss( Str>Ta~ Tes,To' T!s<V/c V_*V( Vo*3Ab#Alog)Am/tC,Ff  G  Git=G[Lu M/~eM(u Oxy@Rec%fRig Scu,Spo}Ssip St/-V %rVi}5Ci#C&'G?IgtMa(eMici#Mp,Na&'Nj$Nn Pam(eRto*Ru!Sa.Se'Ss>Ta%$U/>Ub#U-'U,Y_2Ag$Ap Es}Ibb]Oitu!2P +P#xeRab#Rc*3Nas%e:0Lou*0Ar,HarpeHel#La* Lip<Lo!Lu<O#Onom+Or-Ou,Ra}Rem Riva(RouU?U!u`0If>Uqu 1Fac Fec%fFig+FortFray Fusi$0Ali}Ar 2Ec,1Abor Arg*Ectr$Eg/tEph/tEveIgib#I%s?O.Ucid Ud 0B=]Bell*Bry$Er|@Issi$M_ O%$Ouvo*P e'Ploy Por,Pri<Ulsi$0Cadr Ch eClaveCo~eDigu Dos}DroitDui!Erg+F/-F m Fou*Gag G(Glob Ig?Jamb JeuLev NemiNuye{Ri~*Roba.Seig[Tas}T_d!T>To' Trav Um  Vah*Viab#Voy Zy?0L+n0Aiss*Arg[At/tAu#Ic +I@m+I Ilog)I[Iso@ItapheO^ReuveRouv Uis/t0U !Uipe0Ig Osi$Re'Up%$0C=>Pad$Pe-P+g#Po*PritQuiv Say S_-S+uSor Ti?TomacTra@0Ag eA]An~eA%^E(d!Endo*Er[lH/olHi^Hn+Ir Off Oi#Onn/tO'd*R/.RoitU@0Phor+0Alu Asi$Enta`I@n-I,Olu%fOqu 1ActAg  Auc Cel]Cit/tClusifCu<Ecu,Emp#Erc H= Hor,I.n-I]Is,O%^Ped>Plor Po}Prim QuisT_sifTrai!Ul,:B#Bu#{Cet&Ci#Ctu!Ibl*Lai<Me{M`#R-'RfeluR(eRou~eSc( T=Tig)Uc$U%fVe'Vori3Bri#C$d D  L(M?M'Ndo*Od=Rm Ro-Rve'S%v=U`#Ut!Vr>3AscoCe]C%fDe#Gu!Latu!Leta.L>eL#ulLm LouLtr N/c N*O#R?Ssu!X 2A* Am?As^At&'E|E~eE'Exi$Oc$O!Uctu Ui@Uvi=2L+Nd +Ngib#Nta(eRc Rg $Rmu]Rtu[Ssi#Ud!Ug eU`]Ulu!Urmi2Agi#Ai<An~*App Aye'Ega&E( El$Em*E[s+E!Iab#Ic%$Iss$Ivo#OidOma.Ont=Ot,Uit2Gi%fI&Re'R+{R%fSi$T':Gn Lax+L +Mbad R/%rRd+nRn*Rrig)Zel#Z$3AntLa%[Lu#Ndar?N =N+NouN%lOlog+O?t!R/iumR?St)lY}3B>C]RafeV!2A-AiveIs}ObeOi!Or+{2Lfe'M?Nf]R.R`#Udr$Uff!UlotUp`#Urm/dUt&2Ad)lAffi%A(eAndApp(AtuitAv*EnatIffu!Il]Imp Ogn Ond Ot&OupeUg U% Uy e2EpardErr>I@Im|veIta!Sta%f3Mnas&Rostat:Bitu@Cho*L&Me|NgarN[t$RicotRm$+Rp$Sard3LiumMato?RbeRiss$Rm(eR$Si,U!{3B n BouLar/tStoi!V 5MardMma.Mo.[N['Nor N&{R@Riz$Rlo.Rm$eRrib#U#{Us<5BlotI#{Ma(Mb#Mi@Mo'R]3Dro?lG+[M[Pno<:2Yl#2Nor U/e4Lici&Lusi$0A.Bib I,M_<Mobi#Muab#PactP i=Plor Po}Prim Pu,0Carn C_d+Ci@ntCl( Colo!Dex Di-Duc%fEditEp%eExactF(iFlig Form Fusi$G  H= Hib Jec,Ju!No-ntOcu]Ond Scri!Sec&Sig[Soli&Sp* S%nctSul,TactT_<Ti?Trig)Tui%fU%#Vasi$V_,Vi,Voqu 3Oni^Rad>ReelRi,0O]2Oi!Res<:GuarIll*MbeNv>Rd(Ug U[Velot3Tab#T$UdiU[s<9Ind!N~ Ng]Ue'UissifUrn=Vi=Y|Ye{5Bi]Ge?ntNiorP$Ris&S%-Te{V_i#:Yak7M$oOs^:BelBi=Bo' C  Cto<Gu[I[Is}I% Mbe|Mel#MpeN-'Nga.N,[P(R.'R?Ur>VaboVo*3Ctu!G=G Gu?SsiveTt!V>Xi^Zard3As<B  B!C_-Cor[E.Ev!Gatu!Go,G)M Mi&M$a@Mpi@Neai!NgotOn-|Qui@S>eS,ThiumTi.Ttor=V!'5Gi^Inta(Is*MbricT +U UrdUt!UveY=5B+Ci@Cra%fE'Gub!Is/tM>eNai!NdiR$T,X){:Ch(eGas(G_taGi^Ig!Ill$In%_Ir+Is$Jor Lax Lefi-Lhe'Li-L#t&MmouthNda,Niab#Nqu/tN&|N)lRath$Rb!R~/dRdiRi%?R^'Rr$R&]Scot&SsifT +lT>eTra^Udi!Ussa@UveXim=3Ch/tC$nuDa`#Dec(Di,Du<Il#'L/.Lod+Mb!Moi!Nac N Nh*Ns$.NtorRc!diRi&R#Ssag Su!T=Teo!Tho@T>Ub#3Au]CrobeEt&Gn$Gr L+uLli$Mi^N-N =Nim=Nor Nu&Rac#Roi,Ssi#X&5Bi#D [El#{Ndi=Ni&'Nna+Not$eNst!Ntag[Nu?ntQ)'R-|Rsu!R% Te'TifU~eUf#Ul(Uss$Ut$Uv/t5L%p#Ni%$Ra`#Re[Rmu!Sc#SeumSic+nTa%$T T)l3Ria@R%l#S,eThi^:Ge'PpeRquoisRr Ta%$Ti$Tu!Ufra.U%^Vi!3Bu#{CtarFas&Ga%$Glig Goc>I.Rve{Ttoy Ur$eUtr$Veu3CheCkelTra&Ve|5B#CifCt'[Ir-'I<t&Ma@Mb!{Mm Rma%fTab#Tif>Toi!Urr*Uve|Va&'Vemb!Vi-5A.Anc I!Isib#M oP%=Q)Tri%f:0E*Jec%fLig Sc'S v Stac#T_*T' 0Casi$Cup E/Tob!Troy Tup]Ulai!0E'Or/t1F_}Fic>Fr*0Ive1Se|S`l$2Fac%fIv>0Bra.Ett!0Ct){Du]E!{Iri^1A#A^Er Ini$PortunPrim T Ti^1A.{An.Bi&D$n E`#G/eG)`Ifi-Ne?ntQ)T+0C`]Mo<Satu!0Ar+0Rag/Rs$T`Trag Vra.0A%$1Y@Y.[1O[:Isib#La-Lma!sLo'@Lp Na~eNdaNgol(Niqu N[|NoramaNt=$PayeP>Po,PyrusRadoxeR-l#Res<Rfum R]Ro#Rra(R<m Rtag Ru!Rv_*Ssi$S&^T [lT+n-Tr$V`l$Voi}Y Ysa.3Ig[Int!La.Lic/L#Lou<Lu~eNdu#Netr Nib#NsifN'+Pi&PlumRdrixRfor Rio@Rmu,Rp#xeRs`R&S Ta#TitTr*Up#2Ara$Ob+O^Ot$Ra<Ysi^0AnoCt'=E-Er!Euv!Lo&N-|Pet&Qu Rog)Sc(eSt$Vo,XelZza2AcardAf$dAis*An A^Astr$A&|E' ExusIa.OmbOng U+Uma.2Chet&Es+E&In&Ir>Iss$Iv!Lai!Lic>L#nLyg$eMma@Mp>Nct)lNd  NeyR%^Si%$S<d Stu!Tag Te|Ti$U-Ula(Um$Urp!Uss(Uvo*2A*+A%^Ec+{Edi!EfixeElu@EnomE<n-E&x&Evo*Imi%fIn-Is$Iv Ob#?O-d Odi.Of$dOg!sO+Oje,Olog)O?n Op!Osp eO&g O)s<Ov beU@n-U[|0Y~o<1BlicC $I}LpeLsarNai<Ni%fPit!Rif>Zz#3Rami@:99AsarE!l#Es%$Ietu@It,O%_t:C(eC$,D+{G$d(I@'Is(L_%rLl$.Mas}Pi@Sa.Tis}Vag V(Y$n 3Ac%fAg*Ali}Anim Cevo*Ci,Clam Col,Cru,Cu]Cyc]Dig Dou,Fai!F#xeForm Fra(Fu.G=+nGi$Gla.Gul>I, Je,Jou La%fLev L+fMar^Me@Mi<M$,Mpl*Mu NardNfortNif]N$c Ntr NvoiPl>Por,Pri<P%#Qu(S veS(e{Soud!SpectS,SultatTabl*T_*Ticu#Tomb Trac Uni$Uss*V/~eViv!Vol&Vulsif3Ches<De|E'Gi@Go]Nc Pos,Sib#S^T)lV=V>e5Che{M/-Mp!N-Nd(Se|S>Ta%fTorTu#U.U`#U#|U%[Y|?5B/BisCheEl#G){In Is<|S S%^3Th?:B]Bo,B!Co~eFariGes<Is*La@LiveL$Lu MediNc%$Ngl>Rcas?Rd(eT' Ug!nuUm$U,Uva.V/tV$n 1AlpelAnda#E]atEnarioEpt!HemaI_-Ind O!Ru%nUlp,1An-Cab#Ch Cou C!,Da%fDui!Ig['Jo'Lec%fMa(eMb]M_-M(=Na&'Nsib#N&n-Par Q)n-Re(R.ntR+{Rru!RumRvi-Sa?V*Vra.Xtup#3D =Ec#Eg Ff]G#Gn=L_-LiciumMp#Nc eNist!Ph$RopSmi^Tu 1I 3Ci=C#DiumIg[{LdatLe`Litu@Lub#Mb!M?`Mno]N@N.'N[t&No!Rc>R%rS+T%<Uc+{Udu!Uff#U#v UpapeUr-U%r Uv_*0Ac+{A%=Eci=H eIr=3Ab#A%$ErnumImulusIpu]RictUd+{Upe'Ylis&0Bli?BstratB%lBv_*C-sC!FfixeGg  Ive'Lfa&P bePpl>Rfa-Rica&R?n Rpri<Rs|tRv+Spect3LlabeMbo#Metr+Nap<NtaxeS&?:BacBl>C%#Il]L_tLism/L$n Mbo'Mi}Ngib#PisQu( Rd RifR%[S<TamiToua.UpeU!|X 3Mo(Mpo!lNa`#Nd!Ne'N*Nsi$Rm( R[Rrib#T(eX&2E?Eor+Erap+Orax0BiaE@Mi@Reli!Ro*SsuT/eT!Tub 5Bogg/L /tMa&Ni^N[|P$y?R~eRd!Rna@Rp`#R!ntR<Rt)TemU~ Urna.Us}X(e2Ac%$AficAgi^Ah*A(An~ Ava`Ef#Emp EsorEu`Ia.Ibun=Ico,Ilog+IompheIp]It' Ivi=Omb$eOncOpic=Oupe|2I#LipeMul&N[lRb(eTe'Toy Y|3Mp/Ph$Pi^R/:0Ues^9Ti?Tras$1Ani?If>I$I^Itai!Iv s3AniumBa(Tic/t0A.I[UelU!0I#Op+:Car?Cc(Gab$dG)Ill/tInc!Is<|Lab#Li<Ll$LveMpi!N`#Pe'R>Se{Ss=S&3C&'Det&Get=Hicu#InardLo-Nd!diN  Ng Ni?{Ntou<Rdu!R(Rn*RrouR}RtuSt$T /Tus&X/tX 3AducAn@Ctoi!D/.DeoG[t&G)'La(Lla.Naig!Ol$P eRe?ntRtuo<RusSa.Se'Si$S^{S)lT=Tes<Tico#Tr(eVa-Vipa!5Ca%$Gu I#Is(Itu!La`#Lc/L%g Lu?Ra-R&xT Ulo*Ya.Yel#:G$:3N$:Cht:3B!NithS&9Olog+";
var accents2 = "e7693&)U*o&)Ry^)*)W))))#X^))))@@)#Wf)m%)#!))AG)&IIAQIIIBIIHJNAgBIILIDJGo)))HIQIIIIA(IGgJHH(BIIxX#)Ou)@*IAAPIIIJHQJ)&QIQPYI(HYAQC%)!))QHJJ@)#)^f*^AXCJ))$%CP))%&m)u)@e^A#G#))W@!(IKK%!(I%))O@QA))@GG#e))))WHJIWh))my@IIBT^)!)HAYGETHI*))!QnUDG)))nBoKAC*HwyQh))$&)G&)UGO)G)))(BX#v**)%O,e7686)I))@)&)gdMP()))ud)p#L))I^FIHYdWG))D@DFV)QA)o%MyTh%*)Z)%)n(XANc^R)YS";
var checksum4 = "0x51deb7ae009149dc61a6bd18a918eb7ac78d2775726c68e598b92d002519b045";
var wordlist4 = null;
var LangFr = class _LangFr extends WordlistOwlA {
  /**
   *  Creates a new instance of the French language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langFr]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("fr", words4, accents2, checksum4);
  }
  /**
   *  Returns a singleton instance of a ``LangFr``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist4 == null) {
      wordlist4 = new _LangFr();
    }
    return wordlist4;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-ja.js
var data = [
  // 4-kana words
  "AQRASRAGBAGUAIRAHBAghAURAdBAdcAnoAMEAFBAFCBKFBQRBSFBCXBCDBCHBGFBEQBpBBpQBIkBHNBeOBgFBVCBhBBhNBmOBmRBiHBiFBUFBZDBvFBsXBkFBlcBjYBwDBMBBTBBTRBWBBWXXaQXaRXQWXSRXCFXYBXpHXOQXHRXhRXuRXmXXbRXlXXwDXTRXrCXWQXWGaBWaKcaYgasFadQalmaMBacAKaRKKBKKXKKjKQRKDRKCYKCRKIDKeVKHcKlXKjHKrYNAHNBWNaRNKcNIBNIONmXNsXNdXNnBNMBNRBNrXNWDNWMNFOQABQAHQBrQXBQXFQaRQKXQKDQKOQKFQNBQNDQQgQCXQCDQGBQGDQGdQYXQpBQpQQpHQLXQHuQgBQhBQhCQuFQmXQiDQUFQZDQsFQdRQkHQbRQlOQlmQPDQjDQwXQMBQMDQcFQTBQTHQrDDXQDNFDGBDGQDGRDpFDhFDmXDZXDbRDMYDRdDTRDrXSAhSBCSBrSGQSEQSHBSVRShYShkSyQSuFSiBSdcSoESocSlmSMBSFBSFKSFNSFdSFcCByCaRCKcCSBCSRCCrCGbCEHCYXCpBCpQCIBCIHCeNCgBCgFCVECVcCmkCmwCZXCZFCdRClOClmClFCjDCjdCnXCwBCwXCcRCFQCFjGXhGNhGDEGDMGCDGCHGIFGgBGVXGVEGVRGmXGsXGdYGoSGbRGnXGwXGwDGWRGFNGFLGFOGFdGFkEABEBDEBFEXOEaBEKSENBENDEYXEIgEIkEgBEgQEgHEhFEudEuFEiBEiHEiFEZDEvBEsXEsFEdXEdREkFEbBEbRElFEPCEfkEFNYAEYAhYBNYQdYDXYSRYCEYYoYgQYgRYuRYmCYZTYdBYbEYlXYjQYRbYWRpKXpQopQnpSFpCXpIBpISphNpdBpdRpbRpcZpFBpFNpFDpFopFrLADLBuLXQLXcLaFLCXLEhLpBLpFLHXLeVLhILdHLdRLoDLbRLrXIABIBQIBCIBsIBoIBMIBRIXaIaRIKYIKRINBINuICDIGBIIDIIkIgRIxFIyQIiHIdRIbYIbRIlHIwRIMYIcRIRVITRIFBIFNIFQOABOAFOBQOaFONBONMOQFOSFOCDOGBOEQOpBOLXOIBOIFOgQOgFOyQOycOmXOsXOdIOkHOMEOMkOWWHBNHXNHXWHNXHDuHDRHSuHSRHHoHhkHmRHdRHkQHlcHlRHwBHWcgAEgAggAkgBNgBQgBEgXOgYcgLXgHjgyQgiBgsFgdagMYgWSgFQgFEVBTVXEVKBVKNVKDVKYVKRVNBVNYVDBVDxVSBVSRVCjVGNVLXVIFVhBVhcVsXVdRVbRVlRhBYhKYhDYhGShxWhmNhdahdkhbRhjohMXhTRxAXxXSxKBxNBxEQxeNxeQxhXxsFxdbxlHxjcxFBxFNxFQxFOxFoyNYyYoybcyMYuBQuBRuBruDMuCouHBudQukkuoBulVuMXuFEmCYmCRmpRmeDmiMmjdmTFmFQiADiBOiaRiKRiNBiNRiSFiGkiGFiERipRiLFiIFihYibHijBijEiMXiWBiFBiFCUBQUXFUaRUNDUNcUNRUNFUDBUSHUCDUGBUGFUEqULNULoUIRUeEUeYUgBUhFUuRUiFUsXUdFUkHUbBUjSUjYUwXUMDUcHURdUTBUrBUrXUrQZAFZXZZaRZKFZNBZQFZCXZGBZYdZpBZLDZIFZHXZHNZeQZVRZVFZmXZiBZvFZdFZkFZbHZbFZwXZcCZcRZRBvBQvBGvBLvBWvCovMYsAFsBDsaRsKFsNFsDrsSHsSFsCXsCRsEBsEHsEfspBsLBsLDsIgsIRseGsbRsFBsFQsFSdNBdSRdCVdGHdYDdHcdVbdySduDdsXdlRdwXdWYdWcdWRkBMkXOkaRkNIkNFkSFkCFkYBkpRkeNkgBkhVkmXksFklVkMBkWDkFNoBNoaQoaFoNBoNXoNaoNEoSRoEroYXoYCoYbopRopFomXojkowXorFbBEbEIbdBbjYlaRlDElMXlFDjKjjSRjGBjYBjYkjpRjLXjIBjOFjeVjbRjwBnXQnSHnpFnLXnINnMBnTRwXBwXNwXYwNFwQFwSBwGFwLXwLDweNwgBwuHwjDwnXMBXMpFMIBMeNMTHcaQcNBcDHcSFcCXcpBcLXcLDcgFcuFcnXcwXccDcTQcrFTQErXNrCHrpFrgFrbFrTHrFcWNYWNbWEHWMXWTR",
  // 5-kana words
  "ABGHABIJAEAVAYJQALZJAIaRAHNXAHdcAHbRAZJMAZJRAZTRAdVJAklmAbcNAjdRAMnRAMWYAWpRAWgRAFgBAFhBAFdcBNJBBNJDBQKBBQhcBQlmBDEJBYJkBYJTBpNBBpJFBIJBBIJDBIcABOKXBOEJBOVJBOiJBOZJBepBBeLXBeIFBegBBgGJBVJXBuocBiJRBUJQBlXVBlITBwNFBMYVBcqXBTlmBWNFBWiJBWnRBFGHBFwXXKGJXNJBXNZJXDTTXSHSXSVRXSlHXCJDXGQJXEhXXYQJXYbRXOfXXeNcXVJFXhQJXhEJXdTRXjdXXMhBXcQTXRGBXTEBXTnQXFCXXFOFXFgFaBaFaBNJaBCJaBpBaBwXaNJKaNJDaQIBaDpRaEPDaHMFamDJalEJaMZJaFaFaFNBaFQJaFLDaFVHKBCYKBEBKBHDKXaFKXGdKXEJKXpHKXIBKXZDKXwXKKwLKNacKNYJKNJoKNWcKDGdKDTRKChXKGaRKGhBKGbRKEBTKEaRKEPTKLMDKLWRKOHDKVJcKdBcKlIBKlOPKFSBKFEPKFpFNBNJNJBQNBGHNBEPNBHXNBgFNBVXNBZDNBsXNBwXNNaRNNJDNNJENNJkNDCJNDVDNGJRNJiDNZJNNsCJNJFNNFSBNFCXNFEPNFLXNFIFQJBFQCaRQJEQQLJDQLJFQIaRQOqXQHaFQHHQQVJXQVJDQhNJQmEIQZJFQsJXQJrFQWbRDJABDBYJDXNFDXCXDXLXDXZDDXsJDQqXDSJFDJCXDEPkDEqXDYmQDpSJDOCkDOGQDHEIDVJDDuDuDWEBDJFgSBNDSBSFSBGHSBIBSBTQSKVYSJQNSJQiSJCXSEqXSJYVSIiJSOMYSHAHSHaQSeCFSepQSegBSHdHSHrFShSJSJuHSJUFSkNRSrSrSWEBSFaHSJFQSFCXSFGDSFYXSFODSFgBSFVXSFhBSFxFSFkFSFbBSFMFCADdCJXBCXaFCXKFCXNFCXCXCXGBCXEJCXYBCXLDCXIBCXOPCXHXCXgBCXhBCXiBCXlDCXcHCJNBCJNFCDCJCDGBCDVXCDhBCDiDCDJdCCmNCpJFCIaRCOqXCHCHCHZJCViJCuCuCmddCJiFCdNBCdHhClEJCnUJCreSCWlgCWTRCFBFCFNBCFYBCFVFCFhFCFdSCFTBCFWDGBNBGBQFGJBCGBEqGBpBGBgQGNBEGNJYGNkOGNJRGDUFGJpQGHaBGJeNGJeEGVBlGVKjGiJDGvJHGsVJGkEBGMIJGWjNGFBFGFCXGFGBGFYXGFpBGFMFEASJEAWpEJNFECJVEIXSEIQJEOqXEOcFEeNcEHEJEHlFEJgFEhlmEmDJEmZJEiMBEUqXEoSREPBFEPXFEPKFEPSFEPEFEPpFEPLXEPIBEJPdEPcFEPTBEJnXEqlHEMpREFCXEFODEFcFYASJYJAFYBaBYBVXYXpFYDhBYCJBYJGFYYbRYeNcYJeVYiIJYZJcYvJgYvJRYJsXYsJFYMYMYreVpBNHpBEJpBwXpQxFpYEJpeNDpJeDpeSFpeCHpHUJpHbBpHcHpmUJpiiJpUJrpsJuplITpFaBpFQqpFGBpFEfpFYBpFpBpFLJpFIDpFgBpFVXpFyQpFuFpFlFpFjDpFnXpFwXpJFMpFTBLXCJLXEFLXhFLXUJLXbFLalmLNJBLSJQLCLCLGJBLLDJLHaFLeNFLeSHLeCXLepFLhaRLZsJLsJDLsJrLocaLlLlLMdbLFNBLFSBLFEHLFkFIBBFIBXFIBaQIBKXIBSFIBpHIBLXIBgBIBhBIBuHIBmXIBiFIBZXIBvFIBbFIBjQIBwXIBWFIKTRIQUJIDGFICjQIYSRIINXIJeCIVaRImEkIZJFIvJRIsJXIdCJIJoRIbBQIjYBIcqXITFVIreVIFKFIFSFIFCJIFGFIFLDIFIBIJFOIFgBIFVXIJFhIFxFIFmXIFdHIFbBIJFrIJFWOBGBOQfXOOKjOUqXOfXBOqXEOcqXORVJOFIBOFlDHBIOHXiFHNTRHCJXHIaRHHJDHHEJHVbRHZJYHbIBHRsJHRkDHWlmgBKFgBSBgBCDgBGHgBpBgBIBgBVJgBuBgBvFgKDTgQVXgDUJgGSJgOqXgmUMgZIJgTUJgWIEgFBFgFNBgFDJgFSFgFGBgFYXgJFOgFgQgFVXgFhBgFbHgJFWVJABVQKcVDgFVOfXVeDFVhaRVmGdViJYVMaRVFNHhBNDhBCXhBEqhBpFhBLXhNJBhSJRheVXhhKEhxlmhZIJhdBQhkIJhbMNhMUJhMZJxNJgxQUJxDEkxDdFxSJRxplmxeSBxeCXxeGFxeYXxepQxegBxWVcxFEQxFLXxFIBxFgBxFxDxFZtxFdcxFbBxFwXyDJXyDlcuASJuDJpuDIBuCpJuGSJuIJFueEFuZIJusJXudWEuoIBuWGJuFBcuFKEuFNFuFQFuFDJuFGJuFVJuFUtuFdHuFTBmBYJmNJYmQhkmLJDmLJomIdXmiJYmvJRmsJRmklmmMBymMuCmclmmcnQiJABiJBNiJBDiBSFiBCJiBEFiBYBiBpFiBLXiBTHiJNciDEfiCZJiECJiJEqiOkHiHKFieNDiHJQieQcieDHieSFieCXieGFieEFieIHiegFihUJixNoioNXiFaBiFKFiFNDiFEPiFYXitFOitFHiFgBiFVEiFmXiFitiFbBiFMFiFrFUCXQUIoQUIJcUHQJUeCEUHwXUUJDUUqXUdWcUcqXUrnQUFNDUFSHUFCFUFEfUFLXUtFOZBXOZXSBZXpFZXVXZEQJZEJkZpDJZOqXZeNHZeCDZUqXZFBQZFEHZFLXvBAFvBKFvBCXvBEPvBpHvBIDvBgFvBuHvQNJvFNFvFGBvFIBvJFcsXCDsXLXsXsXsXlFsXcHsQqXsJQFsEqXseIFsFEHsFjDdBxOdNpRdNJRdEJbdpJRdhZJdnSJdrjNdFNJdFQHdFhNkNJDkYaRkHNRkHSRkVbRkuMRkjSJkcqDoSJFoEiJoYZJoOfXohEBoMGQocqXbBAFbBXFbBaFbBNDbBGBbBLXbBTBbBWDbGJYbIJHbFQqbFpQlDgQlOrFlVJRjGEBjZJRnXvJnXbBnEfHnOPDngJRnxfXnUJWwXEJwNpJwDpBwEfXwrEBMDCJMDGHMDIJMLJDcQGDcQpHcqXccqNFcqCXcFCJRBSBRBGBRBEJRBpQTBNFTBQJTBpBTBVXTFABTFSBTFCFTFGBTFMDrXCJrXLDrDNJrEfHrFQJrFitWNjdWNTR",
  // 6-kana words
  "AKLJMANOPFASNJIAEJWXAYJNRAIIbRAIcdaAeEfDAgidRAdjNYAMYEJAMIbRAFNJBAFpJFBBIJYBDZJFBSiJhBGdEBBEJfXBEJqXBEJWRBpaUJBLXrXBIYJMBOcfXBeEfFBestXBjNJRBcDJOBFEqXXNvJRXDMBhXCJNYXOAWpXONJWXHDEBXeIaRXhYJDXZJSJXMDJOXcASJXFVJXaBQqXaBZJFasXdQaFSJQaFEfXaFpJHaFOqXKBNSRKXvJBKQJhXKEJQJKEJGFKINJBKIJjNKgJNSKVElmKVhEBKiJGFKlBgJKjnUJKwsJYKMFIJKFNJDKFIJFKFOfXNJBSFNJBCXNBpJFNJBvQNJBMBNJLJXNJOqXNJeCXNJeGFNdsJCNbTKFNwXUJQNFEPQDiJcQDMSJQSFpBQGMQJQJeOcQyCJEQUJEBQJFBrQFEJqDXDJFDJXpBDJXIMDGiJhDIJGRDJeYcDHrDJDVXgFDkAWpDkIgRDjDEqDMvJRDJFNFDJFIBSKclmSJQOFSJQVHSJQjDSJGJBSJGJFSECJoSHEJqSJHTBSJVJDSViJYSZJNBSJsJDSFSJFSFEfXSJFLXCBUJVCJXSBCJXpBCXVJXCJXsXCJXdFCJNJHCLIJgCHiJFCVNJMChCJhCUHEJCsJTRCJdYcCoQJCCFEfXCFIJgCFUJxCFstFGJBaQGJBIDGQJqXGYJNRGJHKFGeQqDGHEJFGJeLXGHIiJGHdBlGUJEBGkIJTGFQPDGJFEqEAGegEJIJBEJVJXEhQJTEiJNcEJZJFEJoEqEjDEqEPDsXEPGJBEPOqXEPeQFEfDiDEJfEFEfepQEfMiJEqXNBEqDIDEqeSFEqVJXEMvJRYXNJDYXEJHYKVJcYYJEBYJeEcYJUqXYFpJFYFstXpAZJMpBSJFpNBNFpeQPDpHLJDpHIJFpHgJFpeitFpHZJFpJFADpFSJFpJFCJpFOqXpFitBpJFZJLXIJFLIJgRLVNJWLVHJMLwNpJLFGJBLFLJDLFOqXLJFUJIBDJXIBGJBIJBYQIJBIBIBOqXIBcqDIEGJFILNJTIIJEBIOiJhIJeNBIJeIBIhiJIIWoTRIJFAHIJFpBIJFuHIFUtFIJFTHOSBYJOEcqXOHEJqOvBpFOkVJrObBVJOncqDOcNJkHhNJRHuHJuHdMhBgBUqXgBsJXgONJBgHNJDgHHJQgJeitgHsJXgJyNagyDJBgZJDrgsVJQgkEJNgkjSJgJFAHgFCJDgFZtMVJXNFVXQfXVJXDJVXoQJVQVJQVDEfXVDvJHVEqNFVeQfXVHpJFVHxfXVVJSRVVmaRVlIJOhCXVJhHjYkhxCJVhWVUJhWiJcxBNJIxeEqDxfXBFxcFEPxFSJFxFYJXyBDQJydaUJyFOPDuYCJYuLvJRuHLJXuZJLDuFOPDuFZJHuFcqXmKHJdmCQJcmOsVJiJAGFitLCFieOfXiestXiZJMEikNJQirXzFiFQqXiFIJFiFZJFiFvtFUHpJFUteIcUteOcUVCJkUhdHcUbEJEUJqXQUMNJhURjYkUFitFZDGJHZJIxDZJVJXZJFDJZJFpQvBNJBvBSJFvJxBrseQqDsVFVJdFLJDkEJNBkmNJYkFLJDoQJOPoGsJRoEAHBoEJfFbBQqDbBZJHbFVJXlFIJBjYIrXjeitcjjCEBjWMNBwXQfXwXOaFwDsJXwCJTRwrCZJMDNJQcDDJFcqDOPRYiJFTBsJXTQIJBTFEfXTFLJDrXEJFrEJXMrFZJFWEJdEWYTlm",
  // 7-kana words
  "ABCDEFACNJTRAMBDJdAcNJVXBLNJEBXSIdWRXErNJkXYDJMBXZJCJaXMNJaYKKVJKcKDEJqXKDcNJhKVJrNYKbgJVXKFVJSBNBYBwDNJeQfXNJeEqXNhGJWENJFiJRQlIJbEQJfXxDQqXcfXQFNDEJQFwXUJDYcnUJDJIBgQDIUJTRDJFEqDSJQSJFSJQIJFSOPeZtSJFZJHCJXQfXCTDEqFGJBSJFGJBOfXGJBcqXGJHNJDGJRLiJEJfXEqEJFEJPEFpBEJYJBZJFYBwXUJYiJMEBYJZJyTYTONJXpQMFXFpeGIDdpJFstXpJFcPDLBVSJRLHQJqXLJFZJFIJBNJDIJBUqXIBkFDJIJEJPTIYJGWRIJeQPDIJeEfHIJFsJXOqGDSFHXEJqXgJCsJCgGQJqXgdQYJEgFMFNBgJFcqDVJwXUJVJFZJchIgJCCxOEJqXxOwXUJyDJBVRuscisciJBiJBieUtqXiJFDJkiFsJXQUGEZJcUJFsJXZtXIrXZDZJDrZJFNJDZJFstXvJFQqXvJFCJEsJXQJqkhkNGBbDJdTRbYJMEBlDwXUJMEFiJFcfXNJDRcNJWMTBLJXC",
  // 8-kana words
  "BraFUtHBFSJFdbNBLJXVJQoYJNEBSJBEJfHSJHwXUJCJdAZJMGjaFVJXEJPNJBlEJfFiJFpFbFEJqIJBVJCrIBdHiJhOPFChvJVJZJNJWxGFNIFLueIBQJqUHEJfUFstOZJDrlXEASJRlXVJXSFwVJNJWD",
  // 9-kana words
  "QJEJNNJDQJEJIBSFQJEJxegBQJEJfHEPSJBmXEJFSJCDEJqXLXNJFQqXIcQsFNJFIFEJqXUJgFsJXIJBUJEJfHNFvJxEqXNJnXUJFQqD",
  // 10-kana words
  "IJBEJqXZJ"
];
var mapping = "~~AzB~X~a~KN~Q~D~S~C~G~E~Y~p~L~I~O~eH~g~V~hxyumi~~U~~Z~~v~~s~~dkoblPjfnqwMcRTr~W~~~F~~~~~Jt";
var _wordlist = null;
function hex(word) {
  return hexlify(toUtf8Bytes(word));
}
var KiYoKu = "0xe3818de38284e3818f";
var KyoKu = "0xe3818de38283e3818f";
function toString(data4) {
  return toUtf8String(new Uint8Array(data4));
}
function loadWords() {
  if (_wordlist !== null) {
    return _wordlist;
  }
  const wordlist10 = [];
  const transform = {};
  transform[toString([227, 130, 154])] = false;
  transform[toString([227, 130, 153])] = false;
  transform[toString([227, 130, 133])] = toString([227, 130, 134]);
  transform[toString([227, 129, 163])] = toString([227, 129, 164]);
  transform[toString([227, 130, 131])] = toString([227, 130, 132]);
  transform[toString([227, 130, 135])] = toString([227, 130, 136]);
  function normalize(word) {
    let result = "";
    for (let i = 0; i < word.length; i++) {
      let kana = word[i];
      const target = transform[kana];
      if (target === false) {
        continue;
      }
      if (target) {
        kana = target;
      }
      result += kana;
    }
    return result;
  }
  function sortJapanese(a, b) {
    a = normalize(a);
    b = normalize(b);
    if (a < b) {
      return -1;
    }
    if (a > b) {
      return 1;
    }
    return 0;
  }
  for (let length = 3; length <= 9; length++) {
    const d = data[length - 3];
    for (let offset = 0; offset < d.length; offset += length) {
      const word = [];
      for (let i = 0; i < length; i++) {
        const k = mapping.indexOf(d[offset + i]);
        word.push(227);
        word.push(k & 64 ? 130 : 129);
        word.push((k & 63) + 128);
      }
      wordlist10.push(toString(word));
    }
  }
  wordlist10.sort(sortJapanese);
  if (hex(wordlist10[442]) === KiYoKu && hex(wordlist10[443]) === KyoKu) {
    const tmp = wordlist10[442];
    wordlist10[442] = wordlist10[443];
    wordlist10[443] = tmp;
  }
  const checksum7 = id(wordlist10.join("\n") + "\n");
  if (checksum7 !== "0xcb36b09e6baa935787fd762ce65e80b0c6a8dabdfbc3a7f86ac0e2c4fd111600") {
    throw new Error("BIP39 Wordlist for ja (Japanese) FAILED");
  }
  _wordlist = wordlist10;
  return wordlist10;
}
var wordlist5 = null;
var LangJa = class _LangJa extends Wordlist {
  /**
   *  Creates a new instance of the Japanese language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langJa]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("ja");
  }
  getWord(index) {
    const words7 = loadWords();
    assertArgument(index >= 0 && index < words7.length, `invalid word index: ${index}`, "index", index);
    return words7[index];
  }
  getWordIndex(word) {
    return loadWords().indexOf(word);
  }
  split(phrase) {
    return phrase.split(/(?:\u3000| )+/g);
  }
  join(words7) {
    return words7.join("\u3000");
  }
  /**
   *  Returns a singleton instance of a ``LangJa``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist5 == null) {
      wordlist5 = new _LangJa();
    }
    return wordlist5;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-ko.js
var data2 = [
  "OYAa",
  "ATAZoATBl3ATCTrATCl8ATDloATGg3ATHT8ATJT8ATJl3ATLlvATLn4ATMT8ATMX8ATMboATMgoAToLbAToMTATrHgATvHnAT3AnAT3JbAT3MTAT8DbAT8JTAT8LmAT8MYAT8MbAT#LnAUHT8AUHZvAUJXrAUJX8AULnrAXJnvAXLUoAXLgvAXMn6AXRg3AXrMbAX3JTAX3QbAYLn3AZLgvAZrSUAZvAcAZ8AaAZ8AbAZ8AnAZ8HnAZ8LgAZ8MYAZ8MgAZ8OnAaAboAaDTrAaFTrAaJTrAaJboAaLVoAaMXvAaOl8AaSeoAbAUoAbAg8AbAl4AbGnrAbMT8AbMXrAbMn4AbQb8AbSV8AbvRlAb8AUAb8AnAb8HgAb8JTAb8NTAb8RbAcGboAcLnvAcMT8AcMX8AcSToAcrAaAcrFnAc8AbAc8MgAfGgrAfHboAfJnvAfLV8AfLkoAfMT8AfMnoAfQb8AfScrAfSgrAgAZ8AgFl3AgGX8AgHZvAgHgrAgJXoAgJX8AgJboAgLZoAgLn4AgOX8AgoATAgoAnAgoCUAgoJgAgoLXAgoMYAgoSeAgrDUAgrJTAhrFnAhrLjAhrQgAjAgoAjJnrAkMX8AkOnoAlCTvAlCV8AlClvAlFg4AlFl6AlFn3AloSnAlrAXAlrAfAlrFUAlrFbAlrGgAlrOXAlvKnAlvMTAl3AbAl3MnAnATrAnAcrAnCZ3AnCl8AnDg8AnFboAnFl3AnHX4AnHbrAnHgrAnIl3AnJgvAnLXoAnLX4AnLbrAnLgrAnLhrAnMXoAnMgrAnOn3AnSbrAnSeoAnvLnAn3OnCTGgvCTSlvCTvAUCTvKnCTvNTCT3CZCT3GUCT3MTCT8HnCUCZrCULf8CULnvCU3HnCU3JUCY6NUCbDb8CbFZoCbLnrCboOTCboScCbrFnCbvLnCb8AgCb8HgCb$LnCkLfoClBn3CloDUDTHT8DTLl3DTSU8DTrAaDTrLXDTrLjDTrOYDTrOgDTvFXDTvFnDT3HUDT3LfDUCT9DUDT4DUFVoDUFV8DUFkoDUGgrDUJnrDULl8DUMT8DUMXrDUMX4DUMg8DUOUoDUOgvDUOg8DUSToDUSZ8DbDXoDbDgoDbGT8DbJn3DbLg3DbLn4DbMXrDbMg8DbOToDboJXGTClvGTDT8GTFZrGTLVoGTLlvGTLl3GTMg8GTOTvGTSlrGToCUGTrDgGTrJYGTrScGTtLnGTvAnGTvQgGUCZrGUDTvGUFZoGUHXrGULnvGUMT8GUoMgGXoLnGXrMXGXrMnGXvFnGYLnvGZOnvGZvOnGZ8LaGZ8LmGbAl3GbDYvGbDlrGbHX3GbJl4GbLV8GbLn3GbMn4GboJTGboRfGbvFUGb3GUGb4JnGgDX3GgFl$GgJlrGgLX6GgLZoGgLf8GgOXoGgrAgGgrJXGgrMYGgrScGgvATGgvOYGnAgoGnJgvGnLZoGnLg3GnLnrGnQn8GnSbrGnrMgHTClvHTDToHTFT3HTQT8HToJTHToJgHTrDUHTrMnHTvFYHTvRfHT8MnHT8SUHUAZ8HUBb4HUDTvHUoMYHXFl6HXJX6HXQlrHXrAUHXrMnHXrSbHXvFYHXvKXHX3LjHX3MeHYvQlHZrScHZvDbHbAcrHbFT3HbFl3HbJT8HbLTrHbMT8HbMXrHbMbrHbQb8HbSX3HboDbHboJTHbrFUHbrHgHbrJTHb8JTHb8MnHb8QgHgAlrHgDT3HgGgrHgHgrHgJTrHgJT8HgLX@HgLnrHgMT8HgMX8HgMboHgOnrHgQToHgRg3HgoHgHgrCbHgrFnHgrLVHgvAcHgvAfHnAloHnCTrHnCnvHnGTrHnGZ8HnGnvHnJT8HnLf8HnLkvHnMg8HnRTrITvFUITvFnJTAXrJTCV8JTFT3JTFT8JTFn4JTGgvJTHT8JTJT8JTJXvJTJl3JTJnvJTLX4JTLf8JTLhvJTMT8JTMXrJTMnrJTObrJTQT8JTSlvJT8DUJT8FkJT8MTJT8OXJT8OgJT8QUJT8RfJUHZoJXFT4JXFlrJXGZ8JXGnrJXLV8JXLgvJXMXoJXMX3JXNboJXPlvJXoJTJXoLkJXrAXJXrHUJXrJgJXvJTJXvOnJX4KnJYAl3JYJT8JYLhvJYQToJYrQXJY6NUJbAl3JbCZrJbDloJbGT8JbGgrJbJXvJbJboJbLf8JbLhrJbLl3JbMnvJbRg8JbSZ8JboDbJbrCZJbrSUJb3KnJb8LnJfRn8JgAXrJgCZrJgDTrJgGZrJgGZ8JgHToJgJT8JgJXoJgJgvJgLX4JgLZ3JgLZ8JgLn4JgMgrJgMn4JgOgvJgPX6JgRnvJgSToJgoCZJgoJbJgoMYJgrJXJgrJgJgrLjJg6MTJlCn3JlGgvJlJl8Jl4AnJl8FnJl8HgJnAToJnATrJnAbvJnDUoJnGnrJnJXrJnJXvJnLhvJnLnrJnLnvJnMToJnMT8JnMXvJnMX3JnMg8JnMlrJnMn4JnOX8JnST4JnSX3JnoAgJnoAnJnoJTJnoObJnrAbJnrAkJnrHnJnrJTJnrJYJnrOYJnrScJnvCUJnvFaJnvJgJnvJnJnvOYJnvQUJnvRUJn3FnJn3JTKnFl3KnLT6LTDlvLTMnoLTOn3LTRl3LTSb4LTSlrLToAnLToJgLTrAULTrAcLTrCULTrHgLTrMgLT3JnLULnrLUMX8LUoJgLVATrLVDTrLVLb8LVoJgLV8MgLV8RTLXDg3LXFlrLXrCnLXrLXLX3GTLX4GgLX4OYLZAXrLZAcrLZAgrLZAhrLZDXyLZDlrLZFbrLZFl3LZJX6LZJX8LZLc8LZLnrLZSU8LZoJTLZoJnLZrAgLZrAnLZrJYLZrLULZrMgLZrSkLZvAnLZvGULZvJeLZvOTLZ3FZLZ4JXLZ8STLZ8ScLaAT3LaAl3LaHT8LaJTrLaJT8LaJXrLaJgvLaJl4LaLVoLaMXrLaMXvLaMX8LbClvLbFToLbHlrLbJn4LbLZ3LbLhvLbMXrLbMnoLbvSULcLnrLc8HnLc8MTLdrMnLeAgoLeOgvLeOn3LfAl3LfLnvLfMl3LfOX8Lf8AnLf8JXLf8LXLgJTrLgJXrLgJl8LgMX8LgRZrLhCToLhrAbLhrFULhrJXLhvJYLjHTrLjHX4LjJX8LjLhrLjSX3LjSZ4LkFX4LkGZ8LkGgvLkJTrLkMXoLkSToLkSU8LkSZ8LkoOYLl3FfLl3MgLmAZrLmCbrLmGgrLmHboLmJnoLmJn3LmLfoLmLhrLmSToLnAX6LnAb6LnCZ3LnCb3LnDTvLnDb8LnFl3LnGnrLnHZvLnHgvLnITvLnJT8LnJX8LnJlvLnLf8LnLg6LnLhvLnLnoLnMXrLnMg8LnQlvLnSbrLnrAgLnrAnLnrDbLnrFkLnrJdLnrMULnrOYLnrSTLnvAnLnvDULnvHgLnvOYLnvOnLn3GgLn4DULn4JTLn4JnMTAZoMTAloMTDb8MTFT8MTJnoMTJnrMTLZrMTLhrMTLkvMTMX8MTRTrMToATMTrDnMTrOnMT3JnMT4MnMT8FUMT8FaMT8FlMT8GTMT8GbMT8GnMT8HnMT8JTMT8JbMT8OTMUCl8MUJTrMUJU8MUMX8MURTrMUSToMXAX6MXAb6MXCZoMXFXrMXHXrMXLgvMXOgoMXrAUMXrAnMXrHgMXrJYMXrJnMXrMTMXrMgMXrOYMXrSZMXrSgMXvDUMXvOTMX3JgMX3OTMX4JnMX8DbMX8FnMX8HbMX8HgMX8HnMX8LbMX8MnMX8OnMYAb8MYGboMYHTvMYHX4MYLTrMYLnvMYMToMYOgvMYRg3MYSTrMbAToMbAXrMbAl3MbAn8MbGZ8MbJT8MbJXrMbMXvMbMX8MbMnoMbrMUMb8AfMb8FbMb8FkMcJXoMeLnrMgFl3MgGTvMgGXoMgGgrMgGnrMgHT8MgHZrMgJnoMgLnrMgLnvMgMT8MgQUoMgrHnMgvAnMg8HgMg8JYMg8LfMloJnMl8ATMl8AXMl8JYMnAToMnAT4MnAZ8MnAl3MnAl4MnCl8MnHT8MnHg8MnJnoMnLZoMnLhrMnMXoMnMX3MnMnrMnOgvMnrFbMnrFfMnrFnMnrNTMnvJXNTMl8OTCT3OTFV8OTFn3OTHZvOTJXrOTOl3OT3ATOT3JUOT3LZOT3LeOT3MbOT8ATOT8AbOT8AgOT8MbOUCXvOUMX3OXHXvOXLl3OXrMUOXvDbOX6NUOX8JbOYFZoOYLbrOYLkoOYMg8OYSX3ObHTrObHT4ObJgrObLhrObMX3ObOX8Ob8FnOeAlrOeJT8OeJXrOeJnrOeLToOeMb8OgJXoOgLXoOgMnrOgOXrOgOloOgoAgOgoJbOgoMYOgoSTOg8AbOjLX4OjMnoOjSV8OnLVoOnrAgOn3DUPXQlrPXvFXPbvFTPdAT3PlFn3PnvFbQTLn4QToAgQToMTQULV8QURg8QUoJnQXCXvQbFbrQb8AaQb8AcQb8FbQb8MYQb8ScQeAlrQeLhrQjAn3QlFXoQloJgQloSnRTLnvRTrGURTrJTRUJZrRUoJlRUrQnRZrLmRZrMnRZrSnRZ8ATRZ8JbRZ8ScRbMT8RbST3RfGZrRfMX8RfMgrRfSZrRnAbrRnGT8RnvJgRnvLfRnvMTRn8AaSTClvSTJgrSTOXrSTRg3STRnvSToAcSToAfSToAnSToHnSToLjSToMTSTrAaSTrEUST3BYST8AgST8LmSUAZvSUAgrSUDT4SUDT8SUGgvSUJXoSUJXvSULTrSU8JTSU8LjSV8AnSV8JgSXFToSXLf8SYvAnSZrDUSZrMUSZrMnSZ8HgSZ8JTSZ8JgSZ8MYSZ8QUSaQUoSbCT3SbHToSbQYvSbSl4SboJnSbvFbSb8HbSb8JgSb8OTScGZrScHgrScJTvScMT8ScSToScoHbScrMTScvAnSeAZrSeAcrSeHboSeJUoSeLhrSeMT8SeMXrSe6JgSgHTrSkJnoSkLnvSk8CUSlFl3SlrSnSl8GnSmAboSmGT8SmJU8",
  "ATLnDlATrAZoATrJX4ATrMT8ATrMX4ATrRTrATvDl8ATvJUoATvMl8AT3AToAT3MX8AT8CT3AT8DT8AT8HZrAT8HgoAUAgFnAUCTFnAXoMX8AXrAT8AXrGgvAXrJXvAXrOgoAXvLl3AZvAgoAZvFbrAZvJXoAZvJl8AZvJn3AZvMX8AZvSbrAZ8FZoAZ8LZ8AZ8MU8AZ8OTvAZ8SV8AZ8SX3AbAgFZAboJnoAbvGboAb8ATrAb8AZoAb8AgrAb8Al4Ab8Db8Ab8JnoAb8LX4Ab8LZrAb8LhrAb8MT8Ab8OUoAb8Qb8Ab8ST8AcrAUoAcrAc8AcrCZ3AcrFT3AcrFZrAcrJl4AcrJn3AcrMX3AcrOTvAc8AZ8Ac8MT8AfAcJXAgoFn4AgoGgvAgoGnrAgoLc8AgoMXoAgrLnrAkrSZ8AlFXCTAloHboAlrHbrAlrLhrAlrLkoAl3CZrAl3LUoAl3LZrAnrAl4AnrMT8An3HT4BT3IToBX4MnvBb!Ln$CTGXMnCToLZ4CTrHT8CT3JTrCT3RZrCT#GTvCU6GgvCU8Db8CU8GZrCU8HT8CboLl3CbrGgrCbrMU8Cb8DT3Cb8GnrCb8LX4Cb8MT8Cb8ObrCgrGgvCgrKX4Cl8FZoDTrAbvDTrDboDTrGT6DTrJgrDTrMX3DTrRZrDTrRg8DTvAVvDTvFZoDT3DT8DT3Ln3DT4HZrDT4MT8DT8AlrDT8MT8DUAkGbDUDbJnDYLnQlDbDUOYDbMTAnDbMXSnDboAT3DboFn4DboLnvDj6JTrGTCgFTGTGgFnGTJTMnGTLnPlGToJT8GTrCT3GTrLVoGTrLnvGTrMX3GTrMboGTvKl3GZClFnGZrDT3GZ8DTrGZ8FZ8GZ8MXvGZ8On8GZ8ST3GbCnQXGbMbFnGboFboGboJg3GboMXoGb3JTvGb3JboGb3Mn6Gb3Qb8GgDXLjGgMnAUGgrDloGgrHX4GgrSToGgvAXrGgvAZvGgvFbrGgvLl3GgvMnvGnDnLXGnrATrGnrMboGnuLl3HTATMnHTAgCnHTCTCTHTrGTvHTrHTvHTrJX8HTrLl8HTrMT8HTrMgoHTrOTrHTuOn3HTvAZrHTvDTvHTvGboHTvJU8HTvLl3HTvMXrHTvQb4HT4GT6HT4JT8HT4Jb#HT8Al3HT8GZrHT8GgrHT8HX4HT8Jb8HT8JnoHT8LTrHT8LgvHT8SToHT8SV8HUoJUoHUoJX8HUoLnrHXrLZoHXvAl3HX3LnrHX4FkvHX4LhrHX4MXoHX4OnoHZrAZ8HZrDb8HZrGZ8HZrJnrHZvGZ8HZvLnvHZ8JnvHZ8LhrHbCXJlHbMTAnHboJl4HbpLl3HbrJX8HbrLnrHbrMnvHbvRYrHgoSTrHgrFV8HgrGZ8HgrJXoHgrRnvHgvBb!HgvGTrHgvHX4HgvHn!HgvLTrHgvSU8HnDnLbHnFbJbHnvDn8Hn6GgvHn!BTvJTCTLnJTQgFnJTrAnvJTrLX4JTrOUoJTvFn3JTvLnrJTvNToJT3AgoJT3Jn4JT3LhvJT3ObrJT8AcrJT8Al3JT8JT8JT8JnoJT8LX4JT8LnrJT8MX3JT8Rg3JT8Sc8JUoBTvJU8AToJU8GZ8JU8GgvJU8JTrJU8JXrJU8JnrJU8LnvJU8ScvJXHnJlJXrGgvJXrJU8JXrLhrJXrMT8JXrMXrJXrQUoJXvCTvJXvGZ8JXvGgrJXvQT8JX8Ab8JX8DT8JX8GZ8JX8HZvJX8LnrJX8MT8JX8MXoJX8MnvJX8ST3JYGnCTJbAkGbJbCTAnJbLTAcJboDT3JboLb6JbrAnvJbrCn3JbrDl8JbrGboJbrIZoJbrJnvJbrMnvJbrQb4Jb8RZrJeAbAnJgJnFbJgScAnJgrATrJgvHZ8JgvMn4JlJlFbJlLiQXJlLjOnJlRbOlJlvNXoJlvRl3Jl4AcrJl8AUoJl8MnrJnFnMlJnHgGbJnoDT8JnoFV8JnoGgvJnoIT8JnoQToJnoRg3JnrCZ3JnrGgrJnrHTvJnrLf8JnrOX8JnvAT3JnvFZoJnvGT8JnvJl4JnvMT8JnvMX8JnvOXrJnvPX6JnvSX3JnvSZrJn3MT8Jn3MX8Jn3RTrLTATKnLTJnLTLTMXKnLTRTQlLToGb8LTrAZ8LTrCZ8LTrDb8LTrHT8LT3PX6LT4FZoLT$CTvLT$GgrLUvHX3LVoATrLVoAgoLVoJboLVoMX3LVoRg3LV8CZ3LV8FZoLV8GTvLXrDXoLXrFbrLXvAgvLXvFlrLXvLl3LXvRn6LX4Mb8LX8GT8LYCXMnLYrMnrLZoSTvLZrAZvLZrAloLZrFToLZrJXvLZrJboLZrJl4LZrLnrLZrMT8LZrOgvLZrRnvLZrST4LZvMX8LZvSlvLZ8AgoLZ8CT3LZ8JT8LZ8LV8LZ8LZoLZ8Lg8LZ8SV8LZ8SbrLZ$HT8LZ$Mn4La6CTvLbFbMnLbRYFTLbSnFZLboJT8LbrAT9LbrGb3LbrQb8LcrJX8LcrMXrLerHTvLerJbrLerNboLgrDb8LgrGZ8LgrHTrLgrMXrLgrSU8LgvJTrLgvLl3Lg6Ll3LhrLnrLhrMT8LhvAl4LiLnQXLkoAgrLkoJT8LkoJn4LlrSU8Ll3FZoLl3HTrLl3JX8Ll3JnoLl3LToLmLeFbLnDUFbLnLVAnLnrATrLnrAZoLnrAb8LnrAlrLnrGgvLnrJU8LnrLZrLnrLhrLnrMb8LnrOXrLnrSZ8LnvAb4LnvDTrLnvDl8LnvHTrLnvHbrLnvJT8LnvJU8LnvJbrLnvLhvLnvMX8LnvMb8LnvNnoLnvSU8Ln3Al3Ln4FZoLn4GT6Ln4JgvLn4LhrLn4MT8Ln4SToMToCZrMToJX8MToLX4MToLf8MToRg3MTrEloMTvGb6MT3BTrMT3Lb6MT8AcrMT8AgrMT8GZrMT8JnoMT8LnrMT8MX3MUOUAnMXAbFnMXoAloMXoJX8MXoLf8MXoLl8MXrAb8MXrDTvMXrGT8MXrGgrMXrHTrMXrLf8MXrMU8MXrOXvMXrQb8MXvGT8MXvHTrMXvLVoMX3AX3MX3Jn3MX3LhrMX3MX3MX4AlrMX4OboMX8GTvMX8GZrMX8GgrMX8JT8MX8JX8MX8LhrMX8MT8MYDUFbMYMgDbMbGnFfMbvLX4MbvLl3Mb8Mb8Mb8ST4MgGXCnMg8ATrMg8AgoMg8CZrMg8DTrMg8DboMg8HTrMg8JgrMg8LT8MloJXoMl8AhrMl8JT8MnLgAUMnoJXrMnoLX4MnoLhrMnoMT8MnrAl4MnrDb8MnrOTvMnrOgvMnrQb8MnrSU8MnvGgrMnvHZ8Mn3MToMn4DTrMn4LTrMn4Mg8NnBXAnOTFTFnOToAToOTrGgvOTrJX8OT3JXoOT6MTrOT8GgrOT8HTpOT8MToOUoHT8OUoJT8OUoLn3OXrAgoOXrDg8OXrMT8OXvSToOX6CTvOX8CZrOX8OgrOb6HgvOb8AToOb8MT8OcvLZ8OgvAlrOgvHTvOgvJTrOgvJnrOgvLZrOgvLn4OgvMT8OgvRTrOg8AZoOg8DbvOnrOXoOnvJn4OnvLhvOnvRTrOn3GgoOn3JnvOn6JbvOn8OTrPTGYFTPbBnFnPbGnDnPgDYQTPlrAnvPlrETvPlrLnvPlrMXvPlvFX4QTMTAnQTrJU8QYCnJlQYJlQlQbGTQbQb8JnrQb8LZoQb8LnvQb8MT8Qb8Ml8Qb8ST4QloAl4QloHZvQloJX8QloMn8QnJZOlRTrAZvRTrDTrRTvJn4RTvLhvRT4Jb8RZrAZrRZ8AkrRZ8JU8RZ8LV8RZ8LnvRbJlQXRg3GboRg3MnvRg8AZ8Rg8JboRg8Jl4RnLTCbRnvFl3RnvQb8SToAl4SToCZrSToFZoSToHXrSToJU8SToJgvSToJl4SToLhrSToMX3STrAlvSTrCT9STrCgrSTrGgrSTrHXrSTrHboSTrJnoSTrNboSTvLnrST4AZoST8Ab8ST8JT8SUoJn3SU6HZ#SU6JTvSU8Db8SU8HboSU8LgrSV8JT8SZrAcrSZrAl3SZrJT8SZrJnvSZrMT8SZvLUoSZ4FZoSZ8JnoSZ8RZrScoLnrScoMT8ScoMX8ScrAT4ScrAZ8ScrLZ8ScrLkvScvDb8ScvLf8ScvNToSgrFZrShvKnrSloHUoSloLnrSlrMXoSl8HgrSmrJUoSn3BX6",
  "ATFlOn3ATLgrDYAT4MTAnAT8LTMnAYJnRTrAbGgJnrAbLV8LnAbvNTAnAeFbLg3AgOYMXoAlQbFboAnDboAfAnJgoJTBToDgAnBUJbAl3BboDUAnCTDlvLnCTFTrSnCYoQTLnDTwAbAnDUDTrSnDUHgHgrDX8LXFnDbJXAcrETvLTLnGTFTQbrGTMnGToGT3DUFbGUJlPX3GbQg8LnGboJbFnGb3GgAYGgAg8ScGgMbAXrGgvAbAnGnJTLnvGnvATFgHTDT6ATHTrDlJnHYLnMn8HZrSbJTHZ8LTFnHbFTJUoHgSeMT8HgrLjAnHgvAbAnHlFUrDlHnDgvAnHnHTFT3HnQTGnrJTAaMXvJTGbCn3JTOgrAnJXvAXMnJbMg8SnJbMnRg3Jb8LTMnJnAl3OnJnGYrQlJnJlQY3LTDlCn3LTJjLg3LTLgvFXLTMg3GTLV8HUOgLXFZLg3LXNXrMnLX8QXFnLX9AlMYLYLXPXrLZAbJU8LZDUJU8LZMXrSnLZ$AgFnLaPXrDULbFYrMnLbMn8LXLboJgJgLeFbLg3LgLZrSnLgOYAgoLhrRnJlLkCTrSnLkOnLhrLnFX%AYLnFZoJXLnHTvJbLnLloAbMTATLf8MTHgJn3MTMXrAXMT3MTFnMUITvFnMXFX%AYMXMXvFbMXrFTDbMYAcMX3MbLf8SnMb8JbFnMgMXrMTMgvAXFnMgvGgCmMnAloSnMnFnJTrOXvMXSnOX8HTMnObJT8ScObLZFl3ObMXCZoPTLgrQXPUFnoQXPU3RXJlPX3RkQXPbrJXQlPlrJbFnQUAhrDbQXGnCXvQYLnHlvQbLfLnvRTOgvJbRXJYrQlRYLnrQlRbLnrQlRlFT8JlRlFnrQXSTClCn3STHTrAnSTLZQlrSTMnGTrSToHgGbSTrGTDnSTvGXCnST3HgFbSU3HXAXSbAnJn3SbFT8LnScLfLnv",
  "AT3JgJX8AT8FZoSnAT8JgFV8AT8LhrDbAZ8JT8DbAb8GgLhrAb8SkLnvAe8MT8SnAlMYJXLVAl3GYDTvAl3LfLnvBUDTvLl3CTOn3HTrCT3DUGgrCU8MT8AbCbFTrJUoCgrDb8MTDTLV8JX8DTLnLXQlDT8LZrSnDUQb8FZ8DUST4JnvDb8ScOUoDj6GbJl4GTLfCYMlGToAXvFnGboAXvLnGgAcrJn3GgvFnSToGnLf8JnvGn#HTDToHTLnFXJlHTvATFToHTvHTDToHTvMTAgoHT3STClvHT4AlFl6HT8HTDToHUoDgJTrHUoScMX3HbRZrMXoHboJg8LTHgDb8JTrHgMToLf8HgvLnLnoHnHn3HT4Hn6MgvAnJTJU8ScvJT3AaQT8JT8HTrAnJXrRg8AnJbAloMXoJbrATFToJbvMnoSnJgDb6GgvJgDb8MXoJgSX3JU8JguATFToJlPYLnQlJlQkDnLbJlQlFYJlJl8Lf8OTJnCTFnLbJnLTHXMnJnLXGXCnJnoFfRg3JnrMYRg3Jn3HgFl3KT8Dg8LnLTRlFnPTLTvPbLbvLVoSbrCZLXMY6HT3LXNU7DlrLXNXDTATLX8DX8LnLZDb8JU8LZMnoLhrLZSToJU8LZrLaLnrLZvJn3SnLZ8LhrSnLaJnoMT8LbFlrHTvLbrFTLnrLbvATLlvLb6OTFn3LcLnJZOlLeAT6Mn4LeJT3ObrLg6LXFlrLhrJg8LnLhvDlPX4LhvLfLnvLj6JTFT3LnFbrMXoLnQluCTvLnrQXCY6LnvLfLnvLnvMgLnvLnvSeLf8MTMbrJn3MT3JgST3MT8AnATrMT8LULnrMUMToCZrMUScvLf8MXoDT8SnMX6ATFToMX8AXMT8MX8FkMT8MX8HTrDUMX8ScoSnMYJT6CTvMgAcrMXoMg8SToAfMlvAXLg3MnFl3AnvOT3AnFl3OUoATHT8OU3RnLXrOXrOXrSnObPbvFn6Og8HgrSnOg8OX8DbPTvAgoJgPU3RYLnrPXrDnJZrPb8CTGgvPlrLTDlvPlvFUJnoQUvFXrQlQeMnoAl3QlrQlrSnRTFTrJUoSTDlLiLXSTFg6HT3STJgoMn4STrFTJTrSTrLZFl3ST4FnMXoSUrDlHUoScvHTvSnSfLkvMXo",
  "AUoAcrMXoAZ8HboAg8AbOg6ATFgAg8AloMXoAl3AT8JTrAl8MX8MXoCT3SToJU8Cl8Db8MXoDT8HgrATrDboOT8MXoGTOTrATMnGT8LhrAZ8GnvFnGnQXHToGgvAcrHTvAXvLl3HbrAZoMXoHgBlFXLg3HgMnFXrSnHgrSb8JUoHn6HT8LgvITvATrJUoJUoLZrRnvJU8HT8Jb8JXvFX8QT8JXvLToJTrJYrQnGnQXJgrJnoATrJnoJU8ScvJnvMnvMXoLTCTLgrJXLTJlRTvQlLbRnJlQYvLbrMb8LnvLbvFn3RnoLdCVSTGZrLeSTvGXCnLg3MnoLn3MToLlrETvMT8SToAl3MbrDU6GTvMb8LX4LhrPlrLXGXCnSToLf8Rg3STrDb8LTrSTvLTHXMnSb3RYLnMnSgOg6ATFg",
  "HUDlGnrQXrJTrHgLnrAcJYMb8DULc8LTvFgGnCk3Mg8JbAnLX4QYvFYHnMXrRUoJnGnvFnRlvFTJlQnoSTrBXHXrLYSUJgLfoMT8Se8DTrHbDb",
  "AbDl8SToJU8An3RbAb8ST8DUSTrGnrAgoLbFU6Db8LTrMg8AaHT8Jb8ObDl8SToJU8Pb3RlvFYoJl"
];
var codes = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*";
function getHangul(code) {
  if (code >= 40) {
    code = code + 168 - 40;
  } else if (code >= 19) {
    code = code + 97 - 19;
  }
  return toUtf8String(new Uint8Array([225, (code >> 6) + 132, (code & 63) + 128]));
}
var _wordlist2 = null;
function loadWords2() {
  if (_wordlist2 != null) {
    return _wordlist2;
  }
  const wordlist10 = [];
  data2.forEach((data4, length) => {
    length += 4;
    for (let i = 0; i < data4.length; i += length) {
      let word = "";
      for (let j = 0; j < length; j++) {
        word += getHangul(codes.indexOf(data4[i + j]));
      }
      wordlist10.push(word);
    }
  });
  wordlist10.sort();
  const checksum7 = id(wordlist10.join("\n") + "\n");
  if (checksum7 !== "0xf9eddeace9c5d3da9c93cf7d3cd38f6a13ed3affb933259ae865714e8a3ae71a") {
    throw new Error("BIP39 Wordlist for ko (Korean) FAILED");
  }
  _wordlist2 = wordlist10;
  return wordlist10;
}
var wordlist6 = null;
var LangKo = class _LangKo extends Wordlist {
  /**
   *  Creates a new instance of the Korean language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langKo]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("ko");
  }
  getWord(index) {
    const words7 = loadWords2();
    assertArgument(index >= 0 && index < words7.length, `invalid word index: ${index}`, "index", index);
    return words7[index];
  }
  getWordIndex(word) {
    return loadWords2().indexOf(word);
  }
  /**
   *  Returns a singleton instance of a ``LangKo``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist6 == null) {
      wordlist6 = new _LangKo();
    }
    return wordlist6;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-it.js
var words5 = "0torea noica!iosorolotaleratelanena%oiadoencotivomai t ca%a0A]Bagl'Bin#E.Is(Oli!Rasi_Rog#0Cade!C[$Cus#E <Hil,I@QuaReRil>Roba+U 0Ag'Deb{DomeEgu#Eri!IpeOtt&Ul&1Fabi,Fe|Fis(F-n Oris`O(R~$0AveEn.E_,Ganc'I!It&OnismoR>*Rume Uzzo4AbardaA Bat)Ber#BoBumeCeCol>E|<FaGeb-Ian.IbiIm[ Lag#Leg)Lie_Lo@/Lusi_Me$Oge$Pa}Pest!Ta,=Ter$T%c'T)veUn$Veo*Z&0Alga`Ani+A!=B{Br#EbaEr~E^s+I]Mas(M[daMir&Mon{O!P'Pli&U, 0A}r@Ag-feAlis+Arch?At-CaCel/Co-D&D!aEl*Ge*Gol&Gus I`Neg&Nid#NoNunc'OnimoT%ipoZi1At>Ertu-OdePari!Pe^ Pogg'P)@Pun Ri,1Ab~AchideAgos+Ald~Anc'Atu-AzzoBit)Chiv'D{Eni,G[ Gi<Gu IaMon?NeseRed#RingaRos S[>SoTef%eZil*0Ciu|Col EpsiEtt>Fal I$O/Pir#P)Sagg'SeSolu Sur@TaT[u T%eT-|0Av>EismoOm>O$TesaTiv&Tor$Tr{Tua,0Sil'Str?Tis+To$moTun$0Anz#E!V[i!Vi(Volge!3Io<O ZimoZur):Be,C}$Ci$CoDessaDi/+Gn#I+L]<L@Le=L/+Lza$Mbi$Ndi!RaondaRba)R}R{$RlumeRoc]Sil>S(Tos+Ttu U,VaVosa3C]FfaLg'LvaNdaNe_,Nig$Nzi=ReRli=Ta3Bi+CiDo<Fi@GaLanc?MboNo]*O*goPedePol&Rban.R-S]|Ses S$n$Son.SturiZzar)2An@At+2Ll{Nif>R@S]Tan>T^$Zzo*2Acc'AdipoA`An}Avu-E.l/Eve|EzzaIgl?Il/n.Ind&Oc]*O@Onzi=Ul*U$2Bbo<CaDi$Ffo<IoLboO$R*<R-s}S(/S+:De|Du]La`)L]*LesseLib)LmoLor?MbusaMe-+M%?Mmi$Mo/Mpa,NapaNde/NeNi$No|N^=PacePel*P{*Pogi)Ppe)P-Psu/RapaceR}ssaR@Ris`Rova=R!|R li=Sacc'S}+Ser`SoS(<S.l*Sua,Tas+Te=T-meU Vil*3Dibi,D-+Fa*Leb!Llul&NaNo<N.simoRam~Rc&R RumeRvel*So?SpoTo2E/Ia)Ic}Iede!Ime-I=IrurgoI+r-0AoClismoFr&G$Lind)O|*R}R)siTr>T+di$UffoVet+Vi,2Ass>In~O)2C]Dar@D%eE!n.G$meLl&Lm#Lo!Lpo(L^v#LzaMaMe+M`n@Mo@Mpu.rMu<Nci(Ndur!Nfer`Ngel&NiugeN<s(Nosce!NsumoN^nuoNveg$Per P'<Pp?Pr~poRazzaRda+R%#Rn%eRol/RpoR!@Rs?R.seSm>S+n.Ttu-V#2A.!Avat+E#Ede!Emo(Esci+E+Ice I=,IsiIt>OceO=}Os++Uc?,Us}2Ci!Cu*Gi$Ll#Po/R#!R(!R_Sci$S de:DoI$L`+Meri$Nie/N$(Nz&T#Van^Vve)3Bu|C[n'Ci(Cli$Col*C! D%#Fin{FormeG$Leg&Lfi$Lir'L+M[zaNot#Nt)Pos{Rapa+Riv&RogaScri|Ser Sider'Sume!Tersi_Vo 3Amet)Cemb!Ed)Fe(Ffu(Geri!Gi+,Luv'Nam>N=nziPin P*`Po*Rad&ReRo|RupoSag'Sc! Sf&Sge*Spos S+nzaSu`$ToVa$Vel Vide!Vor#5B*<C[.Ga=,G`LceM#M[~Min&N@*NoRmi!TeT !Vu Zzi=2AgoUi@2Bb'Bit&Ca,NaOmoPl%eRatu):0A$0Ces(CoLissiO$m?0E-I]/I,I r?Uc&2Emon?LiOismoReg'4Abor#Argi!Egan.Enc#E|Ev&F>I}MoSaU(0An#B,`Es(I)O^_Oz'<Pir>U*0Dem>Du)Erg?FasiO.}Tr&Zi`1A^.I*goI(d'O},Pu!0U#!0Ar'BaBo(EdeEmi+Ige!Met>OeOsi_Ran.0Ago$AmeAnimeAudi!CaEmp'Erc{Ib{Ig[.Is.!I OfagoOrt#O(Pan(P!s(S[zaSoTe(Tim&Ton?T)(Ult&0Il>N>Rus]To0ClideoRopa0A(Id[zaIt#Olu Viva:Bbr~Cc[daChi)L]Migl?Na,Nfa-NgoN+s`ReRfal/Ri$(R`]Sc?S (Sul*T%&ToVo*(3Bb!Co/DeG#LpaLt)Mmi=Nde!Nome$Rm[ R)R^,Ssu-S^_T+U@3AbaDuc?FaGur#LoNanzaNest-Ni!O!S},S>Ume2A]<Am[]EboEm`Ori@U[.Uo)2B>Cacc?Co(Der#Gl'La+Lc*!Lgo!Nd[.Net>N?N+=Rb{Rchet+Res+Rm~R='RoR.zzaRz&Sf#S(2A}s(A=Assi$A.l*Eccet+E=+Es]IgoOlli$OndeUga,Ut+2Ci/+Cs?Gg[.Lmi<L_Man.Me|Mo(NeNz'<O]RboRgo<Ro!SoTi,:Bb?$FfeLa.oLli=LoppoMbe)M`Ranz?RboRofa$Rzo<S@|Sol'Str>T Ud'ZeboZzel/3CoLa^=L(Mel*Mm#NeN{!N='No^poRgo2Epar@Iacc'Isa0Al*LdaNep)Oc&Oiel*Or$OveR#Ro<T++Udiz'Ur#Us 2Obu*U^<1Omo0BbaLfM{Mmo<Nf'N=Ver$2Aci,A@Af>AmmoAndeAtt&A_(Az?E}EggeIfo<Ig'InzaOt+Uppo2Adag$A'An Ard&FoId&::0Ern#0O=0Ent>Ill'O*RaR>Roge$2Ie<Na)Nor#4A!Le(Log>Lude!0Bal*Bevu Boc]Bu Ma<Mer(Mol#Pac]Pe PiegoPor P)n+0Al&Arc&At^_Can C[d'Chi$Cisi_Clu(Cont)C)c'CuboDagi<D?Do,Ed{Fat^Fil&Fli|Gagg'Geg$G,seGor@G)s(Nes]O@!Oltr&Ond#Sa$Se|SiemeSonn?Suli=Tas#Te)To=]Tu{Umidi!Vali@VeceV{1Erbo,Not>O.siP~1IdeLandaOn>Rig#Ror&0Ol#O poTer>Titu Tr%e0Al?Er&:::Bb)Birin C}Cer#Cri`Cu=D@veGoMpoNcet+N.r=R@(RgaRingeSt-T[zaTi$TtugaVag=Vo)3Ga,Gge)MboN.zzaNzaO<P!Si_Ss#S T.-,VaVig#3Be)DoEv{L/Matu-Mit&Mpi@Ne&NguaQui@RaR~S}TeTig'V!a5CandaDeG~Mb&Nd-Nge_QuaceR[zoToT.r?5CeCid#Ma}Mi$(NgoPoPpo*SingaS(T :Cab)Cchi=Ce)Cin#Da`G>Gl?G<.G)Iol~LafedeLg-@Lin.(Lsa$L Lumo!NaNc?N@r/Ngi&Nifes N=)Nov-NsardaN^deNubr'PpaR#=Rci!Ret+RmoRsup'Sche-Ssa?S^$Te-s(Tr>/T <Tu)Zur}3And)C}n>Ce=.DesimoDit&GaLassaLisLod?NingeNoN(/Rcur'R[daR*Schi$SeSse!S *Tal*To@T.!3Agol&CaCel'Che,C)boDol*E,Gl'!La$Li.MosaNe-,NiNo!Ri$R^l*Sce/SsivaS Sur&TezzaTig&T-T.n.4Emon>0Del*Dif~Du*Ga$G'LeLos(Nas.)N]Ndi=Ne+r'Ni,No $N(<Nt#Nvi(RaRde!Rs%#St)Tiv#TosegaT V[zaVim[ Zzo5C}CosaFfaGhe|G='La|Li<l*L^p*Mm?N Ove!Ra,SaS]*S~Te_,To:BabboF+Nomet)Rci(R%eRr#Sce!Str&Tu-,Ut~Vigl'3Bu*saC)siGa^_Goz'Mme$Ofi+Re|R_Ssu$Ttu$Ut-,VeV)t>3Cch?NfaTi@5Bi,Ci_DoMeMi=Rd>R`,RvegeseSt-$T&Tiz?Ttur$Vel/5C,oL/Me)O_Tri!Vo/Z?,:Si0Bedi!BligoElis]L'O*So, 0Cas'<Ch'Cid[.Cor!!Cult&RaUl#0Ier$Or&1Fer+Fri!Fusc#0Ge|GiNu$4AndeseFa|I#IvaOg-m`T!0Agg'Bel>B-EgaIss'<0Do(E!IceNi_)O!_,Ta1Er#In'<Pos 1A]*AfoDi<Ecchi$Ef%eFa$Gan>Igi<Izzon.MaMegg'Na^_O*g'R[@Ribi,T[s?T~Za+Zo0A!Cur&MosiPeda,Pi.SaSid&Ta]*Te0I.ReTago$TimoTob!1A,EstI$Ipa)Oc{UnqueVi&3Io:Cche|CeCif>Del/D)<EseGaGi=Lazzi=Les&Lli@LoLudeN@)N<l*O*O=zzoPr~Rabo/Rcel/Re!Rgo*RiRl#Ro/R^!Rv[zaRz?,Ssi_St%}Tac}To*g?TtumeVo<3Cc#Dal&Do=,Gg'Lo(N&Nd%eNi(/Nnu Nomb-Ns&N /PePi+Rbe<R]r(R@n#Rfor&Rgame=R'@Rmes(R$Rp,s(Rsua(Rtug'Rva(S#!Sis+SoS^fe)Ta*T^<Tu/n.Zzo3Ace!An+At^$Cci$CozzaEgaEt-Ffe)G?`Gol'G)LaLife)L*/Lo+Mpan.Ne+N=No*Ogg?OmboRamideRet>Ri.RolisiTo<Zz>2AceboAn&As`A+$E=r'2ChezzaDe)(DismoEs?Ggi&L[+Ligo$Ll%eLmoni.Lpet+L(Lt)=Lve!M%eMo@)N.Po*(Rfi@Ro(Rpo-R!R++SaSi^_Sses(Stul#Tass'Te!2AnzoAssiAt~Eclu(Ed~Efis(Egi#Elie_Eme!E$t&Epar#Es[zaE.s Eval(I`IncipeIv#Ob,`Ocu-Odur!OfumoOge|OlungaOmessaO$meOpos+O)gaO.(OvaUd[.Ug=Ur{0Iche1Bbl>D~Gil#G$LceL{Lsan.Nt&PazzoPil/Ro:99Ad)Al]saAsiE!/O+:C]l D@pp'D~,Dun#Ff~GazzoG'<G$Mar)MingoMoNdag'N l&P#Pi=Pp!(Satu-Schi#S[.Sseg=St!l*TaVvedu 3A,Cepi!Cin Clu+Cond{Cupe)Dd{Dime!Gal#Gist)Go/G!s(Laz'<M&Mo N=Pl~Prime!Put&SaSid[.Spon(S+u)TeTi=Tor~T^f~Voc#3Assun Badi!Bel,B!zzoCar~C]Ceve!C%l#Cor@C!du D>*Dur!Fas&F,s(For`Fug'G&Gett#Ghel*Lass#Lev#Ma<!MbalzoMed'Morch'Nasci+N})NforzoN$_Nom#Nsav{N c]Nunc?Nv[i!Par#Petu Pie$Port&P!saPuli!Sa+Sch'ServaSibi,SoSpe|S )Sult#S_l Tar@Teg$Tm>T)_Un'<VaVer(Vinci+Vol Zo`5BaBot>Bus Cc?CoDagg'De!D{!G{Ll'Mant>Mpe!Nz'Sol&SpoTan.Ton@Tu/Vesc'5BizzoBr~GaLli$Mi<Mo)(O*PeSs&St>:B#Bbi&Bot#Go`Las(Ldatu-Lgem`Liv&Lmo<Lo<Lt&Lu L_Pe!Pi@Por{Race$R}smoR S((Telli.Ti-Tol*Tur$Va=V'Zi#0Adigl'AlzoAnc#Ar-At.!Av&End&Irci&Locc#Occi#Rin&Ruffo<Uff&0Ab)(Ad[zaA/Ambi&Anda*Apo/Ar(A.n&Av#El En>Ett)HedaHie=IarpaI[zaInde!IppoI)ppoI_*Ler&Odel/Olp{Ompar Onfor Opri!Or+Os(<OzzeseRibaRoll&Ru^n'Uder?Ul !Uo/U)Us&0Ebit&Ogan&0C}tu-Con@Da$Gg'/G=l#G!g#Gu{Lci#Let^_L/Lvagg'Mafo)Mbr&MeMin#Mp!N(N^!Pol Qu[zaRa+Rb#Re$R'Rp[.R-gl'Rvi!S^=To/T^`=0Ace*Ald&Am#Arzo(At%#E-IdaIl#IngeOc#Oder&OgoOl^!Orz#Ra|Rutt#Ugg{Um&U(0Abel*Arb#Onfi&Orb'Rass#Uar@1Bi*C]meEr-G/G$!L[z'L/baMbo*Mpat>Mul#Nfon?Ngo*Nist)NoN.siNu(idePar'S`S ,Tu#2It+Ogatu-Ove$0Arr{Emor#En^ E-l@IlzoOnt&Ott#Uss#0Elli!Erv#O@0BbalzoBr'C]r(C?,Da,Ffi|G$Ld#L[<Li@L/zzoLoLubi,Lv[.Mat>M`NdaNe|Nnife)Pi!Ppe(P-Rge!Rpas(Rri(R(R.gg'R_l#Spi)S+T^,0AdaAl/Arge!A /Av[ Azzo/EcieEdi!Eg<!E/tu-E-nzaEs(!Ett-,Ezz#IaIgo*(Ill#I$(I-,L[di@Or^_O(RangaRec&Ron#RuzzoUn^$0Uil*0Ad%&O l#1Abi,Ac]AffaAgn&Amp#Ant'Arnu Ase-Atu E*EppaErzoI,|I`IrpeIva,Izzo(On#Or>RappoReg#Ridu*Rozz&Ru|Ucc&UfoUp[@0B[t)C](Do!Gger{GoL+$On&PerboPpor Rgel#R)g#Ssur)Tu-0Ag&EdeseEgl'El&Enu Ez?IluppoIs+Izze-Ol+Uot&:Bac]Bul#Cci&Citur$LeLis`$Mpo<Nni$RaRdi_Rg#RiffaRp&R+rugaS Tt>Ver=Vo/+Zza3CaCn>Lefo$Me-r'MpoMu N@<Ne)Ns'<N+]*O!`RmeR-zzoRze|SiSser#St#T)T ?3F&Gel/Mb)N P>Pog-foRagg'RoTan'To*Tuban.Z'Zzo<5Cc&L,r&L Mbo/MoNfoNsil/Paz'Po*g?PpaRbaRn&R)<R -S}$Ssi!S+tu-Ta$2Aboc]AcheaAfi/Aged?Alc'Amon Ans{Apa$Ar!As*]Att#AveEcc?Emol'Espo*Ibu Iche]Ifogl'Il*InceaIoIs.zzaItur#Ivel/OmbaO$OppoO|/Ov&Ucc#2Batu-Ff#Lipa$Mul Nis?Rb&Rchi$TaTe/:0Ic#0Cel*Ci(!0I!I^_1FaF%'0Ua,4IsseTim#0A$I,Orismo0Ci<|Ge!Ghe!seI]r$If%#I($I+r'Te0Vo0Upa1Aga$G[zaLo0AnzaA C{Ig$*U-'0Ensi,IlizzoOp?:Can.Ccin#Gabon@Gli#LangaLgoL>L,t+Lo)(Lut&L_/Mpa+Ng&N{(NoN+gg'Nve-Po!Ra$Rc#R?n.S}3Det+DovaDu Ge+,I]*Lc)Li=Llu LoceN#Ndemm?N RaceRba,Rgog=Rif~RoRru}Rt~,Sc~Ssil*S+,Te-$Tri=Tus 3Andan.B-n.C[daChingoCi=nzaDim&Gil?G< Go!LeL/$MiniNc{!O/Pe-Rgo/Ro*goRu,n S](S'<SpoSsu Su-TaTel*T^`VandaVi@Zi&5CeGaLa^,Le!LpeRagi<5L}$::::Mpog=N=Pp#T.-Vor-3Fi)Lan.LoNze)Rbi$3Be|N]R]<T 5L/T>5Cche)Fo*LuPpa";
var checksum5 = "0x5c1362d88fd4cf614a96f3234941d29f7d37c08c5292fde03bf62c2db6ff7620";
var wordlist7 = null;
var LangIt = class _LangIt extends WordlistOwl {
  /**
   *  Creates a new instance of the Italian language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langIt]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("it", words5, checksum5);
  }
  /**
   *  Returns a singleton instance of a ``LangIt``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist7 == null) {
      wordlist7 = new _LangIt();
    }
    return wordlist7;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-pt.js
var words6 = "0arad!ototealirertainrasoent hoandoaR#riareha!aroele'oronul0Aca%AixoAl A%rDuz'El]Er$IsmoO$ R<g(Revi Rig Rup$S*$Solu$Sur=Ut[0Ab#Alm Amp Anh A,Ei.El( En ErvoEss E$naHa.IdezImaI}#Irr Lam LiveOlhidaOmod Opl Ord Um~ Us?0Ap.EgaEnt_Ep$Equ Er-%EsivoEusI<%ItivoJetivoJun$M' Or Qu''UboV(,Vog#0R}ave0As.Er'EtivoIn?Iv` Li$Lu-%R}.0Ach Arr As&/Enci Iliz Io)It#O+R! Res%Rup U d Ul]2O`h Ud Us.1AmedaArmeAstr Av<caB(gueB*oCat+De@EcrimEgr@Er.FaceF*e%GumHeioI Ica%I- Inh Ivi Mof^Oc Pis%T( TitudeUc* Ug UnoUsivoVo0Aci A=rA[loAss BasBi-%EixaEniz I=Is$,Iz!eOl?On$ O_,Ost+P  Pli Pola0Ag+maAlis Arqu@A$m@DaimeElExoG~ Im JoOm&@Ot#Sio,T(i|Uid!eUnci Zol1Ag?Alp Anh#EgoEli=Ert^Es Eti%I$Lau,Lic^OioOn.Os)R-dizRov 0Uec(0AmeAn]A+C^D-%E@Ej Eni$Es)Gilo,GolaMaQuivoRai&Reba%Risc Rob>um S-&T(i&TigoVo[=0F&.Il#P' S?S* So&/Sun$Tr&0Ac#Adu+Al/A[f E End(Er_EuIng'Ir?IvoOl{oRac Revi=RizU&Um0Di$rM-.R>o+TismoT|@Tu 0Ali An%Ar@Ent&Es,I?Is Ul,1Ila1Ar E=Ei%Ulejo:B BosaC&]uCh `C@GagemI<oIl I}e)Ir_Ixis)J~ Le@LizaLsaN&Nd{aN/N'Nque%Ra$Rb#R}es>+c>~/Se#S)n%Ta)Te=rTidaTomTuc Unil]3B(IjoIr^IsebolLd!eLezaLgaLisc Ndi$Ng&aNz(RimbauRl*d>_Sou_XigaZ(_3CoCu=En&Foc&Furc G|naLhe%Mest[Mo$rOlog@OmboOsf(aPol Rr-$Scoi$Sne$SpoSsex$TolaZ _2Ind#OcoOque 2A$BagemC#CejoChec]Ico.L^LetimL]LoMb{oNdeNecoNi)Rb~h>d>e&R+c]V*oXe?2AncoAsaAvezaEuIgaIl/Inc OaOchu+Onze O$Uxo2C]DismoF LeRacoScaS$Z*a:B<aB`oBideBoBri$CauCet^C/r_CiqueDast_De#Fez&IaqueIp'aIxo%J#JuLafrioLc~ Ld{aLibr Lm<%Lo)M^Mbis)MisaMomilaMp<]Mufl Navi&Nc` Ne)NguruN/$Nive%NoaNs#N.Nu=Pac/P`aP* Po.Pric/Pt?PuzRacolRb}oRde&Rec>imb Rn{oRpe%R['>)zRv&/SacoScaSeb[S%loS~oT a)Tiv UleUs?U%l V&oV(na3BolaDil]G}]Lebr L~ Nou+N,N%ioRc Rr#R%'oRvejaTimV^2Aco)Al{aAm#Ap^ArmeAticeAveEfeEg^E'oEqueIco%If[In`oOc&/Ov(UmboU.Uva0CatrizCl}eD!eD['aEn%G<aM-$N$Nz><d>cui$Rurg@T 2A[zaE_Ic OneUbe2A=Ag'Ba@B($rBr C^El/Ent_E,Gum`oIb'IfaIo%L L{aLh(Lid'Lme@L}oLunaM<=Mb* M-.MitivaMov(MplexoMumNc]N=rNec.Nfu,Ng` Nhec(Njug Nsum'Nt+$Nvi%Op( P{oPi?PoQue%lRagemRdi&Rne)R}h>p|&R[ioR%joRuj>voSs-oS%laT}e%U_UveVilZ*]2A%+AvoEcheE=rEmeErEspoI^Im*&Io~oIseItic Os)UaUz{o2B<oEcaId#JoLat+Lm* Lp Ltu+Mpr'Nh#Pi=RativoRr&Rs R$Sp'S% T`o:MascoT 3Ba%rBi.BocheB~h C&queCim&CliveCo%C[.D&Dic#Duz'FesaFum G`oG+uGus.It#Ix La$rLeg#L*e L}gaM<daMit'Moli=Ntis)P-#Pil PoisP[ssaPur Riv>+m SafioSbo.Sc<,S-/Sfi#Sgas%Sigu&SlizeSmam SovaSpesaS)queSvi T&h T-$rT} Tri$UsaV(Vi=Vot#Z-a3Ag+maAle$Da)Fu,Gi.Lat#Lu-%M*u'Nast@Nh{oOceseRe$Sc[)Sf ceSp oSque%Ssip S)n%T?UrnoV(,Vi,rV~g Z(5Br?L|i=M?M*#NativoNz`>m-%Rs&SagemUr#U$r2EnagemIbleOg @2El EndeE$PloQues><%Vi=,:1Lod'O Olog@0Ific It&Uc#1Ei$Etiv 3E.1Ab| Eg(Ei$rEncoEv?Im* Ogi 0B goBol#Br~/Buti=EndaErg'Is,rPat@P-/P*#Polg P[goPurr Ul?0CaixeC-#Ch-%C}t_Deus Doss Faix Fei%FimGaj#G-/Glob Gom#G+x Gu@Jo La.Qu<$Raiz Rol#Rug SaioSe^S*oSop#T<$Te#Tid!eT|.Tr^T~/V(g Vi#Volv(XameX($Xof[Xu$1Id(me0Uip 0E$Gui=Ra)VaVil]0B<j B`$CamaColaCri)Cu)F*geFol F[g Fum#GrimaM&%P<$P`/PigaP}jaP[i)Pum Qu(daTacaT{aTic Tof#T[laTu=Vazi 0AnolIque)0F|i>opeu0Acu Ap| AsivoEntu&Id-%Olu'1Ag(oAl Am* A$Aus$Ces,Ci.Clam Ecu.EmploIb'Ig-%On( P<d'P`'P' Pl< Pos$P[s,P~s T(noT*$T+$:Bric B~o,Ce)Ci&DaDigaIxaL L)Mili Nd<goNf +N$cheRd#R`oR*h>of>p>tu+T@T|V|i)X*aZ-da3Ch#Ijo^I+n%L*oM**oNdaNoR>i#RrugemRv(S%j T&Ud&3ApoB_seC Ch{oGur#L{aL/LmeLtr RmezaSg^Ssu+TaV`aX?Xo2AcidezAm*goAn`aEch^O+Utu Uxo2C&C*/Foc GoGue%IceLg#Lhe$Rj Rmig>noR%ScoSsa2Aga)AldaAngoAscoA%rnoE'aEn%E.IezaI,Itu+On]Ustr U%'a2G'L<oLigemNd NgoNilR?Rio,Tebol:B i$B*e%DoIa$IolaIvo)LegaL/L*]Loc]Nh RagemRfoRg&oRimpoRoup>+faSodu$S$TaTil/Ve)Z`a3L#Le@LoM^M(Mi=N(o,NgivaNi&NomaN_Ologi>?Rm* S,S$r3Nas)Nc<aNg#Raf>*o2Aci&IcoseOb&Orio,2ElaIabaLfeLpe Rdu+Rje)R_S$,T{aV(n 2AcejoAdu&Afi%Al]AmpoAn^Atui$Ave$AxaEgoElh EveIloIs&/I.@Os,O%scoUd#Unhi=U)2AcheA+niAx*imEr[ I Inc/Is#LaLo,Ru:Bi.Rm}@S%V(3C.eRd Res@Si.3A$B(n D+.EnaNoPismoPnosePo%ca5JeLofo%MemNes$Nr#Rm}&Sped 5M|#:Te2E@O,2N|#RejaU<a4E,HaUdi=Um* Ustr 0AgemEd@$En,ErsivoIn-%It?Ort&Pac$Ped'Pl<%P|Pr-saPuneUniz 0Al?Ap$AtivoC-,Ch Cid'Clu'Col|Deci,D'e$Du$rEficazEr-%F<tilFes.F*i$Flam F|m&F+$rG('Ibi=Ici&ImigoJe.Oc-%O=_Ov?OxQuie$Scri$Se$Sist'Spe$rSt& S~$Tac$Tegr&Tim Toc#TrigaVa,rV(noVic$Voc 0Gur%2Ani<oOniz Re&Rit#0CaEn$Ol#Qu{o0Ali<o:N{oNg^N)R ac>dimR_SmimToV&iZida3Jum9An*]Elh^G?I>n&Rr Vem5BaDeuDocaIzLg?L/R#Ris)RoS)::B edaB|&C[C)n%Dril/G )GoaJeMb(M-.M* MpejoNchePid P,R<j>{>gu+S<]St_T(&Ti=V<daVou+Vr?X<%Z(3Ald!eB[G#G-d Gis)IgoIlo Itu+Mb[%MeNh?Ntil]OaSmaS%TivoT['oV VezaVi.3B(&Bi=D( G G{oMi.Mo{oMp?NdaNe N]gemQuidezS)gemSu+T|&V_XaX{a5C?Cu$rJis)MboNaNgeNt+RdeT#T(@Ucu+UsaUv 5ArCidezC_Ne)St[T?Va:CacoCe%Ch#CioD{aDr*]Gna)G[zaI|IsL<d_L]Lo%LucoMiloMo{oMu%N^Nc]Nda$NequimN/,Niv`aNobr NsaN%rNuse Pe#Qu* Rc?Resi>fimRgemR*/Rmi)Ro$RquiseR[coR%loRujoSco%Sm|+SsagemStig Tag&T(noT*&Tu.Xil 3D&]DidaDusaGaf}eIgaL<c@L/rMb_M|i&N*oNosNsagemNt&Rec(Rg~/S^Scl SmoSqui)St[T!eTeo_T+gemX(Xic<o3C_G&]Gr Lag[L- Lh M#N( N/caNist_N|@OloR<%RtiloStur 5Cid!eD(noD~ EdaErIn/I)Ldu+LezaL/L*e%LuscoN)n]Quec><goRcegoR=moR-aSaicoSque%S.daT`TimToTriz5DaI$La)Lh(L.Ndi&Ni=R&h>c/Sc~ SeuSic&:Ci}&D?JaMo_R*>r#Sc(TivaTu[zaV&]Veg Vio3Bl*aB~o,GativaGoci Gri$Rvo,TaUr&VascaVo{o3N N/TidezV` 5B[zaI%IvaMe M*&Rdes%R% T Tici TurnoV`oVil/Vo5Bl#DezM(&Pci&Tr'Vem:0Cec#Edec(JetivoRig#Scu_S%t+T(Tur 0Id-%Io,Orr(Ulis)Up#2Eg<%EnsivaEr-daIc*aUsc#0Iva4Ar@Eo,H Iv{a0B_Ele%Is,It'0D~#E_,Tem1Ci}&Er?On-%OrtunoOs$1ArBi.DemD*&F<a$GasmoG~/I-t&IgemIun=LaTo=xoV&/0Cil S^SoT-.0Imismo0S!@T}oTub_Vi=0El]Ul 1Id Ig- :Ca$Ci-%Co%Ctu D @Dr*/G GodeIn`Ir IsagemLav+Lest+Lhe)Li$Lm^Lpi.Nc^N`aNfle$NquecaN)n&PagaioP`^P'oRaf*>ci&Rd&RedeRtidaSmoSs#S%lTam T-%T* T_noUl^Us 3C~i D& Dest[D@t+D+G^I$r&IxeLeLic<oNcaNdur N{aN]scoNs?N%Rceb(Rfei$Rgun)Ri$Rmit'Rn>plexoRsi<>%nceRucaSc#SquisaS,aTisc 3AdaC#Ed!eGm-$Last+Lh#Lo.M-)Nc`NguimN]No%N.On{oPocaQue%R<h>esRue)Sc S$laT<gaVe%2An)Aque)At*aEbeuUmagemUvi&1Eu0DaE'aEtisaLeg^Lici Lu-%Lvil/M MbaNd( N.@P~o,R)Ssu'St&TeUp U,Vo 2A@Anc]A$AxeEceEd?Efei$Emi Ens Ep  Esil]E%x$Ev-'Ez Ima)IncesaIsmaIv#Oces,Odu$Ofe)Oibi=Oje$Ome%rOpag OsaO%$rOv?2Blic DimL Lm} Ls{aNh&N'PiloRezaX?:99Ad+Ant@Ar$AseEbr EdaEijoEn%Eri=Im}oInaIosque:B<^BiscoCh Ci} Di&I In]IoIvaJ^L#M&Ng(Nhu+P!u+P`PidezPosaQue%Rid!eS<%Scun/Sg Sp?S%'aSur Taz<aTo{a3AlezaAnim Av(Baix B`deBol C#C-%CheioCiboC|d Cru.Cu DeDim'D}daDuzidaEnvioF* Flet'Fog F[scoFugi G&@GimeG+In#I$rJei.LativoM?M-=M|,Nov#P oP`'Ple$Pol/P[saPudi Qu((S-]Sfri Sga.Sid'Solv(Spei$SsacaS)n%Sum'T&/T(T' Tom^T+.V` Vi,rVol)3Ac/CaGidezGo_,M NgueS^ScoS}/5B&oChe=D^DeioDov@E=rLe)M<oNc S#S{aS$TaT{oT*aT~ UcoUpaXo5B_Gi=Go,IvoMoPest[S,:B|Ci ColaCud'DioF'aGaGr^Ib_L^L{oLg#LivaLpic Lsic]L.Lv?Mb Mu+iN Nf}aNgueNid!ePa$Rd>g-$Rje)Tur Ud!eXof}eZ}&3C C~ DaD-$Di#Do,Du$rGm-$G[=Gun=IvaLe$LvagemM<&M-%N?N/rNsu&Nt#P #Rei>*g>+RvoTemb_T|3GiloLhue)Lic}eMetr@Mpat@M~ N&Nc(oNg~ NopseN$ni>-eRiTu#5B(<oB+C|_G_JaLdaLetr L%'oMbrioNa)Nd Neg Nh?NoP+noQue%Rr'R%ioSsegoTaqueT(r V#Z*/5Aviz BidaBm(,B,loBt+'Ca)Ces,CoDes%FixoG?G('Jei$Lfa$M'OrP(i|Plic Pos$Prim'Rd*>fis)Rp[s>[&Rt'Sp'oS%n$:B`aBle%Bu^C/G `aLh(LoLvezM</Mb|imMpaNg-%N$P Pioc>dioRef>j>+xaTuagemUr*oXativoXis)3Atr&C(Ci=Cl#Dio,IaIm Lef}eLh#Mp(oN-%N,rN.Rm&RnoRr-oSeSou+St#ToXtu+Xugo3A+G`aJoloMbr MidezNgi=N%'oRagemT~ 5Al]C]L( LiceM^Mil/N`Ntu+Pe%R>ci=RneioRqueRr!>$S.UcaUp{aX*a2Ab&/Acej Adu$rAfeg Aje$AmaAnc ApoAs{oAt?Av E*oEm(Epid EvoIagemIboIcicloId-%Ilog@Ind!eIploItur Iunf&Oc Ombe)OvaUnfoUque2B~ C<oDoLipaPiRboRm>quesaT` T|i&:7V 3Bigo0HaId!eIf|me3Olog@SoTigaUbu0A=InaUfru':C*aDi G o,I=,LaL-%Lid!eLo[sN)gemQu{oR<d>e)Rr(Sc~ Sil]S,u+Z Zio3A=D Ge.Ic~ L{oLhiceLu=Nce=rNdav&N( Nt[Rb&Rd!eRe?Rg}h>m`/RnizRs R%n%SpaSti=T|i&3Adu$AgemAj Atu+Br?D{aDr @ElaGaG-%Gi G| L ejoNcoNhe)NilOle)R!>tudeSi.S$Tr&V{oZ*/5A=rArG&L<%LeibolL)gemLumo,Nt!e5L$Vuz`a::D[zRope3QueRe.Rife3Ng ::Ng#Rp 3BuL?9Mb Olog@5Mbi=";
var checksum6 = "0x2219000926df7b50d8aa0a3d495826b988287df4657fbd100e6fe596c8f737ac";
var wordlist8 = null;
var LangPt = class _LangPt extends WordlistOwl {
  /**
   *  Creates a new instance of the Portuguese language Wordlist.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langPt]] should suffice.
   *
   *  @_ignore:
   */
  constructor() {
    super("pt", words6, checksum6);
  }
  /**
   *  Returns a singleton instance of a ``LangPt``, creating it
   *  if this is the first time being called.
   */
  static wordlist() {
    if (wordlist8 == null) {
      wordlist8 = new _LangPt();
    }
    return wordlist8;
  }
};

// node_modules/ethers/lib.esm/wordlists/lang-zh.js
var data3 = "}aE#4A=Yv&co#4N#6G=cJ&SM#66|/Z#4t&kn~46#4K~4q%b9=IR#7l,mB#7W_X2*dl}Uo~7s}Uf&Iw#9c&cw~6O&H6&wx&IG%v5=IQ~8a&Pv#47$PR&50%Ko&QM&3l#5f,D9#4L|/H&tQ;v0~6n]nN<di,AM=W5%QO&ka&ua,hM^tm=zV=JA=wR&+X]7P&NB#4J#5L|/b[dA}tJ<Do&6m&u2[U1&Kb.HM&mC=w0&MW<rY,Hq#6M}QG,13&wP}Jp]Ow%ue&Kg<HP<D9~4k~9T&I2_c6$9T#9/[C5~7O~4a=cs&O7=KK=An&l9$6U$8A&uD&QI|/Y&bg}Ux&F2#6b}E2&JN&kW&kp=U/&bb=Xl<Cj}k+~5J#6L&5z&9i}b4&Fo,ho(X0_g3~4O$Fz&QE<HN=Ww]6/%GF-Vw=tj&/D&PN#9g=YO}cL&Of&PI~5I&Ip=vU=IW#9G;0o-wU}ss&QR<BT&R9=tk$PY_dh&Pq-yh]7T,nj.Xu=EP&76=cI&Fs*Xg}z7$Gb&+I=DF,AF=cA}rL#7j=Dz&3y<Aa$52=PQ}b0(iY$Fa}oL&xV#6U=ec=WZ,xh%RY<dp#9N&Fl&44=WH*A7=sh&TB&8P=07;u+&PK}uh}J5#72)V/=xC,AB$k0&f6;1E|+5=1B,3v]6n&wR%b+&xx]7f=Ol}fl;+D^wG]7E;nB;uh^Ir&l5=JL,nS=cf=g5;u6|/Q$Gc=MH%Hg#5d%M6^86=U+$Gz,l/,ir^5y&Ba&/F-IY&FI&be%IZ#77&PW_Nu$kE(Yf&NX]7Z,Jy&FJ(Xo&Nz#/d=y7&MX<Ag}Z+;nE]Dt(iG#4D=13&Pj~4c%v8&Zo%OL&/X#4W<HR&ie~6J_1O(Y2=y5=Ad*cv_eB#6k&PX:BU#7A;uk&Ft&Fx_dD=U2;vB=U5=4F}+O&GN.HH:9s=b0%NV(jO&IH=JT}Z9=VZ<Af,Kx^4m&uJ%c6,6r;9m#+L}cf%Kh&F3~4H=vP}bu,Hz|++,1w]nv}k6;uu$jw*Kl*WX&uM[x7&Fr[m7$NO&QN]hu=JN}nR^8g#/h(ps|KC;vd}xz=V0}p6&FD$G1#7K<bG_4p~8g&cf;u4=tl}+k%5/}fz;uw<cA=u1}gU}VM=LJ=eX&+L&Pr#4U}p2:nC,2K]7H:jF&9x}uX#9O=MB<fz~8X~5m&4D&kN&u5%E/(h7(ZF&VG<de(qM|/e-Wt=3x(a+,/R]f/&ND$Ro&nU}0g=KA%kH&NK$Ke<dS}cB&IX~5g$TN]6m=Uv,Is&Py=Ef%Kz#+/%bi&+A<F4$OG&4C&FL#9V<Zk=2I_eE&6c]nw&kq$HG}y+&A8$P3}OH=XP]70%IS(AJ_gH%GZ&tY&AZ=vb~6y&/r=VI=Wv<Zi=fl=xf&eL}c8}OL=MJ=g8$F7=YT}9u=0+^xC}JH&nL^N0~4T]K2,Cy%OC#6s;vG(AC^xe^cG&MF}Br#9P;wD-7h$O/&xA}Fn^PC]6i]7G&8V$Qs;vl(TB~73~4l<mW&6V=2y&uY&+3)aP}XF;LP&kx$wU=t7;uy<FN&lz)7E=Oo*Y+;wI}9q}le;J6&Ri&4t&Qr#8B=cb&vG=J5|Ql(h5<Yy~4+}QD,Lx=wn%K/&RK=dO&Pw,Q9=co%4u;9u}g0@6a^4I%b0=zo|/c&tX=dQ=OS#+b=yz_AB&wB&Pm=W9$HP_gR=62=AO=ti=hI,oA&jr&dH=tm&b6$P2(x8=zi;nG~7F;05]0n[Ix&3m}rg=Xp=cd&uz]7t;97=cN;vV<jf&FF&F1=6Q&Ik*Kk&P4,2z=fQ]7D&3u,H0=d/}Uw<ZN<7R}Kv;0f$H7,MD]7n$F0#88~9Z%da=by;+T#/u=VF&fO&kr^kf<AB]sU,I5$Ng&Pz;0i&QD&vM=Yl:BM;nJ_xJ]U7&Kf&30,3f|Z9*dC)je_jA&Q4&Kp$NH(Yz#6S&Id%Ib=KX,AD=KV%dP}tW&Pk^+E_Ni=cq,3R}VZ(Si=b+}rv;0j}rZ]uA,/w(Sx&Jv$w9&4d&wE,NJ$Gy=J/]Ls#7k<ZQ<Y/&uj]Ov$PM;v3,2F&+u:up=On&3e,Jv;90=J+&Qm]6q}bK#+d~8Y(h2]hA;99&AS=I/}qB&dQ}yJ-VM}Vl&ui,iB&G3|Dc]7d=eQ%dX%JC_1L~4d^NP;vJ&/1)ZI#7N]9X[bQ&PL=0L(UZ,Lm&kc&IR}n7(iR<AQ<dg=33=vN}ft}au]7I,Ba=x9=dR~6R&Tq=Xi,3d$Nr&Bc}DI&ku&vf]Dn,/F&iD,Ll&Nw=0y&I7=Ls=/A&tU=Qe}Ua&uk&+F=g4=gh=Vj#+1&Qn}Uy*44#5F,Pc&Rz*Xn=oh=5W;0n_Nf(iE<Y7=vr=Zu]oz#5Z%mI=kN=Bv_Jp(T2;vt_Ml<FS&uI=L/&6P]64$M7}86<bo%QX(SI%IY&VK=Al&Ux;vv;ut*E/%uh<ZE|O3,M2(yc]yu=Wk&tp:Ex}hr,Cl&WE)+Z=8U}I2_4Q,hA_si=iw=OM=tM=yZ%Ia=U7;wT}b+;uo=Za}yS!5x}HD}fb#5O_dA;Nv%uB(yB;01(Sf}Fk;v7}Pt#8v<mZ#7L,/r&Pl~4w&f5=Ph$Fw_LF&8m,bL=yJ&BH}p/*Jn}tU~5Q;wB(h6]Df]8p^+B;E4&Wc=d+;Ea&bw$8C&FN,DM=Yf}mP~5w=fT#6V=mC=Fi=AV}jB&AN}lW}aH#/D)dZ;hl;vE}/7,CJ;31&w8,hj%u9_Js=jJ&4M~8k=TN&eC}nL&uc-wi&lX}dj=Mv=e2#6u=cr$uq$6G]8W}Jb:nm=Yg<b3(UA;vX&6n&xF=KT,jC,De&R8&oY=Zv&oB]7/=Z2&Oa}bf,hh(4h^tZ&72&Nx;D2&xL~5h~40)ZG)h+=OJ&RA]Bv$yB=Oq=df,AQ%Jn}OJ;11,3z&Tl&tj;v+^Hv,Dh(id=s+]7N&N3)9Q~8f,S4=uW=w4&uX,LX&3d]CJ&yp&8x<b2_do&lP=y/<cy_dG=Oi=7R(VH(lt_1T,Iq_AA;12^6T%k6#8K[B1{oO<AU[Bt;1b$9S&Ps<8T=St{bY,jB(Zp&63&Uv$9V,PM]6v&Af}zW[bW_oq}sm}nB&Kq&gC&ff_eq_2m&5F&TI}rf}Gf;Zr_z9;ER&jk}iz_sn<BN~+n&vo=Vi%97|ZR=Wc,WE&6t]6z%85(ly#84=KY)6m_5/=aX,N3}Tm&he&6K]tR_B2-I3;u/&hU&lH<AP=iB&IA=XL;/5&Nh=wv<BH#79=vS=zl<AA=0X_RG}Bw&9p$NW,AX&kP_Lp&/Z(Tc]Mu}hs#6I}5B&cI<bq&H9#6m=K9}vH(Y1(Y0#4B&w6,/9&gG<bE,/O=zb}I4_l8<B/;wL%Qo<HO[Mq=XX}0v&BP&F4(mG}0i}nm,EC=9u{I3,xG&/9=JY*DK&hR)BX=EI=cx=b/{6k}yX%A+&wa}Xb=la;wi^lL;0t}jo&Qb=xg=XB}iO<qo{bR=NV&8f=a0&Jy;0v=uK)HK;vN#6h&jB(h/%ud&NI%wY.X7=Pt}Cu-uL&Gs_hl%mH,tm]78=Lb^Q0#7Y=1u<Bt&+Q=Co_RH,w3;1e}ux<aU;ui}U3&Q5%bt]63&UQ|0l&uL}O7&3o,AV&dm|Nj(Xt*5+(Uu&Hh(p7(UF=VR=Bp^Jl&Hd[ix)9/=Iq]C8<67]66}mB%6f}bb}JI]8T$HA}db=YM&pa=2J}tS&Y0=PS&y4=cX$6E,hX,XP&nR;04,FQ&l0&Vm_Dv#5Y~8Z=Bi%MA]6x=JO:+p,Az&9q,Hj~6/}SD=K1:EJ}nA;Qo#/E]9R,Ie&6X%W3]61&v4=xX_MC=0q;06(Xq=fs}IG}Dv=0l}o7$iZ;9v&LH&DP-7a&OY,SZ,Kz,Cv&dh=fx|Nh,F/~7q=XF&w+;9n&Gw;0h}Z7<7O&JK(S7&LS<AD<ac=wo<Dt&zw%4B=4v#8P;9o~6p*vV=Tm,Or&I6=1q}nY=P0=gq&Bl&Uu,Ch%yb}UY=zh}dh}rl(T4_xk(YA#8R*xH,IN}Jn]7V}C4&Ty}j3]7p=cL=3h&wW%Qv<Z3=f0&RI&+S(ic_zq}oN&/Y=z1;Td=LW=0e=OI(Vc,+b^ju(UL;0r:Za%8v=Rp=zw&58&73&wK}qX]6y&8E)a2}WR=wP^ur&nQ<cH}Re=Aq&wk}Q0&+q=PP,Gc|/d^k5,Fw]8Y}Pg]p3=ju=ed}r5_yf&Cs]7z$/G<Cm&Jp&54_1G_gP_Ll}JZ;0u]k8_7k(Sg]65{9i=LN&Sx&WK,iW&fD&Lk{9a}Em-9c#8N&io=sy]8d&nT&IK(lx#7/$lW(Td<s8~49,3o<7Y=MW(T+_Jr&Wd,iL}Ct=xh&5V;v4&8n%Kx=iF&l2_0B{B+,If(J0,Lv;u8=Kx-vB=HC&vS=Z6&fU&vE^xK;3D=4h=MR#45:Jw;0d}iw=LU}I5=I0]gB*im,K9}GU,1k_4U&Tt=Vs(iX&lU(TF#7y,ZO}oA&m5#5P}PN}Uz=hM<B1&FB<aG,e6~7T<tP(UQ_ZT=wu&F8)aQ]iN,1r_Lo&/g:CD}84{J1_Ki&Na&3n$jz&FE=dc;uv;va}in}ll=fv(h1&3h}fp=Cy}BM(+E~8m}lo%v7=hC(T6$cj=BQ=Bw(DR,2j=Ks,NS|F+;00=fU=70}Mb(YU;+G&m7&hr=Sk%Co]t+(X5_Jw}0r}gC(AS-IP&QK<Z2#8Q$WC]WX}T2&pG_Ka,HC=R4&/N;Z+;ch(C7,D4$3p_Mk&B2$8D=n9%Ky#5z(CT&QJ#7B]DC]gW}nf~5M;Iw#80}Tc_1F#4Z-aC}Hl=ph=fz,/3=aW}JM}nn;DG;vm}wn,4P}T3;wx&RG$u+}zK=0b;+J_Ek{re<aZ=AS}yY#5D]7q,Cp}xN=VP*2C}GZ}aG~+m_Cs=OY#6r]6g<GS}LC(UB=3A=Bo}Jy<c4}Is;1P<AG}Op<Z1}ld}nS=1Z,yM&95&98=CJ(4t:2L$Hk=Zo}Vc;+I}np&N1}9y=iv}CO*7p=jL)px]tb^zh&GS&Vl%v/;vR=14=zJ&49|/f]hF}WG;03=8P}o/&Gg&rp;DB,Kv}Ji&Pb;aA^ll(4j%yt}+K$Ht#4y&hY]7Y<F1,eN}bG(Uh%6Z]t5%G7;+F_RE;it}tL=LS&Da=Xx(S+(4f=8G=yI}cJ}WP=37=jS}pX}hd)fp<A8=Jt~+o$HJ=M6}iX=g9}CS=dv=Cj(mP%Kd,xq|+9&LD(4/=Xm&QP=Lc}LX&fL;+K=Op(lu=Qs.qC:+e&L+=Jj#8w;SL]7S(b+#4I=c1&nG_Lf&uH;+R)ZV<bV%B/,TE&0H&Jq&Ah%OF&Ss(p2,Wv&I3=Wl}Vq;1L&lJ#9b_1H=8r=b8=JH(SZ=hD=J2#7U,/U#/X~6P,FU<eL=jx,mG=hG=CE&PU=Se(qX&LY=X6=y4&tk&QQ&tf=4g&xI}W+&mZ=Dc#7w}Lg;DA;wQ_Kb(cJ=hR%yX&Yb,hw{bX_4X;EP;1W_2M}Uc=b5(YF,CM&Tp^OJ{DD]6s=vF=Yo~8q}XH}Fu%P5(SJ=Qt;MO]s8<F3&B3&8T(Ul-BS*dw&dR<87}/8]62$PZ]Lx<Au}9Q]7c=ja=KR,Go,Us&v6(qk}pG&G2=ev^GM%w4&H4]7F&dv]J6}Ew:9w=sj-ZL}Ym$+h(Ut(Um~4n=Xs(U7%eE=Qc_JR<CA#6t<Fv|/I,IS,EG<F2(Xy$/n<Fa(h9}+9_2o&N4#7X<Zq|+f_Dp=dt&na,Ca=NJ)jY=8C=YG=s6&Q+<DO}D3=xB&R1(lw;Qn<bF(Cu|/B}HV=SS&n7,10&u0]Dm%A6^4Q=WR(TD=Xo<GH,Rj(l8)bP&n/=LM&CF,F5&ml=PJ;0k=LG=tq,Rh,D6@4i=1p&+9=YC%er_Mh;nI;0q=Fw]80=xq=FM$Gv;v6&nc;wK%H2&Kj;vs,AA=YP,66}bI(qR~5U=6q~4b$Ni=K5.X3$So&Iu(p+]8G=Cf=RY(TS_O3(iH&57=fE=Dg_Do#9z#7H;FK{qd_2k%JR}en&gh_z8;Rx}9p<cN_Ne,DO;LN_7o~/p=NF=5Y}gN<ce<C1,QE]Wv=3u<BC}GK]yq}DY&u/_hj=II(pz&rC,jV&+Z}ut=NQ;Cg-SR_ZS,+o=u/;Oy_RK_QF(Fx&xP}Wr&TA,Uh&g1=yr{ax[VF$Pg(YB;Ox=Vy;+W(Sp}XV%dd&33(l/]l4#4Y}OE=6c=bw(A7&9t%wd&N/&mo,JH&Qe)fm=Ao}fu=tH";
var deltaData = "FAZDC6BALcLZCA+GBARCW8wNCcDDZ8LVFBOqqDUiou+M42TFAyERXFb7EjhP+vmBFpFrUpfDV2F7eB+eCltCHJFWLFCED+pWTojEIHFXc3aFn4F68zqjEuKidS1QBVPDEhE7NA4mhMF7oThD49ot3FgtzHFCK0acW1x8DH1EmLoIlrWFBLE+y5+NA3Cx65wJHTaEZVaK1mWAmPGxgYCdxwOjTDIt/faOEhTl1vqNsKtJCOhJWuio2g07KLZEQsFBUpNtwEByBgxFslFheFbiEPvi61msDvApxCzB6rBCzox7joYA5UdDc+Cb4FSgIabpXFAj3bjkmFAxCZE+mD/SFf/0ELecYCt3nLoxC6WEZf2tKDB4oZvrEmqFkKk7BwILA7gtYBpsTq//D4jD0F0wEB9pyQ1BD5Ba0oYHDI+sbDFhvrHXdDHfgFEIJLi5r8qercNFBgFLC4bo5ERJtamWBDFy73KCEb6M8VpmEt330ygCTK58EIIFkYgF84gtGA9Uyh3m68iVrFbWFbcbqiCYHZ9J1jeRPbL8yswhMiDbhEhdNoSwFbZrLT740ABEqgCkO8J1BLd1VhKKR4sD1yUo0z+FF59Mvg71CFbyEhbHSFBKEIKyoQNgQppq9T0KAqePu0ZFGrXOHdKJqkoTFhYvpDNyuuznrN84thJbsCoO6Cu6Xlvntvy0QYuAExQEYtTUBf3CoCqwgGFZ4u1HJFzDVwEy3cjcpV4QvsPaBC3rCGyCF23o4K3pp2gberGgFEJEHo4nHICtyKH2ZqyxhN05KBBJIQlKh/Oujv/DH32VrlqFdIFC7Fz9Ct4kaqFME0UETLprnN9kfy+kFmtQBB0+5CFu0N9Ij8l/VvJDh2oq3hT6EzjTHKFN7ZjZwoTsAZ4Exsko6Fpa6WC+sduz8jyrLpegTv2h1EBeYpLpm2czQW0KoCcS0bCVXCmuWJDBjN1nQNLdF58SFJ0h7i3pC3oEOKy/FjBklL70XvBEEIWp2yZ04xObzAWDDJG7f+DbqBEA7LyiR95j7MDVdDViz2RE5vWlBMv5e4+VfhP3aXNPhvLSynb9O2x4uFBV+3jqu6d5pCG28/sETByvmu/+IJ0L3wb4rj9DNOLBF6XPIODr4L19U9RRofAG6Nxydi8Bki8BhGJbBAJKzbJxkZSlF9Q2Cu8oKqggB9hBArwLLqEBWEtFowy8XK8bEyw9snT+BeyFk1ZCSrdmgfEwFePTgCjELBEnIbjaDDPJm36rG9pztcEzT8dGk23SBhXBB1H4z+OWze0ooFzz8pDBYFvp9j9tvFByf9y4EFdVnz026CGR5qMr7fxMHN8UUdlyJAzlTBDRC28k+L4FB8078ljyD91tUj1ocnTs8vdEf7znbzm+GIjEZnoZE5rnLL700Xc7yHfz05nWxy03vBB9YGHYOWxgMQGBCR24CVYNE1hpfKxN0zKnfJDmmMgMmBWqNbjfSyFCBWSCGCgR8yFXiHyEj+VtD1FB3FpC1zI0kFbzifiKTLm9yq5zFmur+q8FHqjoOBWsBPiDbnCC2ErunV6cJ6TygXFYHYp7MKN9RUlSIS8/xBAGYLzeqUnBF4QbsTuUkUqGs6CaiDWKWjQK9EJkjpkTmNCPYXL";
var _wordlist3 = {
  zh_cn: null,
  zh_tw: null
};
var Checks = {
  zh_cn: "0x17bcc4d8547e5a7135e365d1ab443aaae95e76d8230c2782c67305d4f21497a1",
  zh_tw: "0x51e720e90c7b87bec1d70eb6e74a21a449bd3ec9c020b01d3a40ed991b60ce5d"
};
var codes2 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
var style = "~!@#$%^&*_-=[]{}|;:,.()<>?";
function loadWords3(locale) {
  if (_wordlist3[locale] != null) {
    return _wordlist3[locale];
  }
  const wordlist10 = [];
  let deltaOffset = 0;
  for (let i = 0; i < 2048; i++) {
    const s = style.indexOf(data3[i * 3]);
    const bytes2 = [
      228 + (s >> 2),
      128 + codes2.indexOf(data3[i * 3 + 1]),
      128 + codes2.indexOf(data3[i * 3 + 2])
    ];
    if (locale === "zh_tw") {
      const common = s % 4;
      for (let i2 = common; i2 < 3; i2++) {
        bytes2[i2] = codes2.indexOf(deltaData[deltaOffset++]) + (i2 == 0 ? 228 : 128);
      }
    }
    wordlist10.push(toUtf8String(new Uint8Array(bytes2)));
  }
  const checksum7 = id(wordlist10.join("\n") + "\n");
  if (checksum7 !== Checks[locale]) {
    throw new Error(`BIP39 Wordlist for ${locale} (Chinese) FAILED`);
  }
  _wordlist3[locale] = wordlist10;
  return wordlist10;
}
var wordlists = {};
var LangZh = class _LangZh extends Wordlist {
  /**
   *  Creates a new instance of the Chinese language Wordlist for
   *  the %%dialect%%, either ``"cn"`` or ``"tw"`` for simplified
   *  or traditional, respectively.
   *
   *  This should be unnecessary most of the time as the exported
   *  [[langZhCn]] and [[langZhTw]] should suffice.
   *
   *  @_ignore:
   */
  constructor(dialect) {
    super("zh_" + dialect);
  }
  getWord(index) {
    const words7 = loadWords3(this.locale);
    assertArgument(index >= 0 && index < words7.length, `invalid word index: ${index}`, "index", index);
    return words7[index];
  }
  getWordIndex(word) {
    return loadWords3(this.locale).indexOf(word);
  }
  split(phrase) {
    phrase = phrase.replace(/(?:\u3000| )+/g, "");
    return phrase.split("");
  }
  /**
   *  Returns a singleton instance of a ``LangZh`` for %%dialect%%,
   *  creating it if this is the first time being called.
   *
   *  Use the %%dialect%% ``"cn"`` or ``"tw"`` for simplified or
   *  traditional, respectively.
   */
  static wordlist(dialect) {
    if (wordlists[dialect] == null) {
      wordlists[dialect] = new _LangZh(dialect);
    }
    return wordlists[dialect];
  }
};

// node_modules/ethers/lib.esm/wordlists/wordlists.js
var wordlists2 = {
  cz: LangCz.wordlist(),
  en: LangEn.wordlist(),
  es: LangEs.wordlist(),
  fr: LangFr.wordlist(),
  it: LangIt.wordlist(),
  pt: LangPt.wordlist(),
  ja: LangJa.wordlist(),
  ko: LangKo.wordlist(),
  zh_cn: LangZh.wordlist("cn"),
  zh_tw: LangZh.wordlist("tw")
};

// node_modules/@scure/bip39/esm/wordlists/french.js
var wordlist9 = `abaisser
abandon
abdiquer
abeille
abolir
aborder
aboutir
aboyer
abrasif
abreuver
abriter
abroger
abrupt
absence
absolu
absurde
abusif
abyssal
acade\u0301mie
acajou
acarien
accabler
accepter
acclamer
accolade
accroche
accuser
acerbe
achat
acheter
aciduler
acier
acompte
acque\u0301rir
acronyme
acteur
actif
actuel
adepte
ade\u0301quat
adhe\u0301sif
adjectif
adjuger
admettre
admirer
adopter
adorer
adoucir
adresse
adroit
adulte
adverbe
ae\u0301rer
ae\u0301ronef
affaire
affecter
affiche
affreux
affubler
agacer
agencer
agile
agiter
agrafer
agre\u0301able
agrume
aider
aiguille
ailier
aimable
aisance
ajouter
ajuster
alarmer
alchimie
alerte
alge\u0300bre
algue
alie\u0301ner
aliment
alle\u0301ger
alliage
allouer
allumer
alourdir
alpaga
altesse
alve\u0301ole
amateur
ambigu
ambre
ame\u0301nager
amertume
amidon
amiral
amorcer
amour
amovible
amphibie
ampleur
amusant
analyse
anaphore
anarchie
anatomie
ancien
ane\u0301antir
angle
angoisse
anguleux
animal
annexer
annonce
annuel
anodin
anomalie
anonyme
anormal
antenne
antidote
anxieux
apaiser
ape\u0301ritif
aplanir
apologie
appareil
appeler
apporter
appuyer
aquarium
aqueduc
arbitre
arbuste
ardeur
ardoise
argent
arlequin
armature
armement
armoire
armure
arpenter
arracher
arriver
arroser
arsenic
arte\u0301riel
article
aspect
asphalte
aspirer
assaut
asservir
assiette
associer
assurer
asticot
astre
astuce
atelier
atome
atrium
atroce
attaque
attentif
attirer
attraper
aubaine
auberge
audace
audible
augurer
aurore
automne
autruche
avaler
avancer
avarice
avenir
averse
aveugle
aviateur
avide
avion
aviser
avoine
avouer
avril
axial
axiome
badge
bafouer
bagage
baguette
baignade
balancer
balcon
baleine
balisage
bambin
bancaire
bandage
banlieue
bannie\u0300re
banquier
barbier
baril
baron
barque
barrage
bassin
bastion
bataille
bateau
batterie
baudrier
bavarder
belette
be\u0301lier
belote
be\u0301ne\u0301fice
berceau
berger
berline
bermuda
besace
besogne
be\u0301tail
beurre
biberon
bicycle
bidule
bijou
bilan
bilingue
billard
binaire
biologie
biopsie
biotype
biscuit
bison
bistouri
bitume
bizarre
blafard
blague
blanchir
blessant
blinder
blond
bloquer
blouson
bobard
bobine
boire
boiser
bolide
bonbon
bondir
bonheur
bonifier
bonus
bordure
borne
botte
boucle
boueux
bougie
boulon
bouquin
bourse
boussole
boutique
boxeur
branche
brasier
brave
brebis
bre\u0300che
breuvage
bricoler
brigade
brillant
brioche
brique
brochure
broder
bronzer
brousse
broyeur
brume
brusque
brutal
bruyant
buffle
buisson
bulletin
bureau
burin
bustier
butiner
butoir
buvable
buvette
cabanon
cabine
cachette
cadeau
cadre
cafe\u0301ine
caillou
caisson
calculer
calepin
calibre
calmer
calomnie
calvaire
camarade
came\u0301ra
camion
campagne
canal
caneton
canon
cantine
canular
capable
caporal
caprice
capsule
capter
capuche
carabine
carbone
caresser
caribou
carnage
carotte
carreau
carton
cascade
casier
casque
cassure
causer
caution
cavalier
caverne
caviar
ce\u0301dille
ceinture
ce\u0301leste
cellule
cendrier
censurer
central
cercle
ce\u0301re\u0301bral
cerise
cerner
cerveau
cesser
chagrin
chaise
chaleur
chambre
chance
chapitre
charbon
chasseur
chaton
chausson
chavirer
chemise
chenille
che\u0301quier
chercher
cheval
chien
chiffre
chignon
chime\u0300re
chiot
chlorure
chocolat
choisir
chose
chouette
chrome
chute
cigare
cigogne
cimenter
cine\u0301ma
cintrer
circuler
cirer
cirque
citerne
citoyen
citron
civil
clairon
clameur
claquer
classe
clavier
client
cligner
climat
clivage
cloche
clonage
cloporte
cobalt
cobra
cocasse
cocotier
coder
codifier
coffre
cogner
cohe\u0301sion
coiffer
coincer
cole\u0300re
colibri
colline
colmater
colonel
combat
come\u0301die
commande
compact
concert
conduire
confier
congeler
connoter
consonne
contact
convexe
copain
copie
corail
corbeau
cordage
corniche
corpus
correct
corte\u0300ge
cosmique
costume
coton
coude
coupure
courage
couteau
couvrir
coyote
crabe
crainte
cravate
crayon
cre\u0301ature
cre\u0301diter
cre\u0301meux
creuser
crevette
cribler
crier
cristal
crite\u0300re
croire
croquer
crotale
crucial
cruel
crypter
cubique
cueillir
cuille\u0300re
cuisine
cuivre
culminer
cultiver
cumuler
cupide
curatif
curseur
cyanure
cycle
cylindre
cynique
daigner
damier
danger
danseur
dauphin
de\u0301battre
de\u0301biter
de\u0301border
de\u0301brider
de\u0301butant
de\u0301caler
de\u0301cembre
de\u0301chirer
de\u0301cider
de\u0301clarer
de\u0301corer
de\u0301crire
de\u0301cupler
de\u0301dale
de\u0301ductif
de\u0301esse
de\u0301fensif
de\u0301filer
de\u0301frayer
de\u0301gager
de\u0301givrer
de\u0301glutir
de\u0301grafer
de\u0301jeuner
de\u0301lice
de\u0301loger
demander
demeurer
de\u0301molir
de\u0301nicher
de\u0301nouer
dentelle
de\u0301nuder
de\u0301part
de\u0301penser
de\u0301phaser
de\u0301placer
de\u0301poser
de\u0301ranger
de\u0301rober
de\u0301sastre
descente
de\u0301sert
de\u0301signer
de\u0301sobe\u0301ir
dessiner
destrier
de\u0301tacher
de\u0301tester
de\u0301tourer
de\u0301tresse
devancer
devenir
deviner
devoir
diable
dialogue
diamant
dicter
diffe\u0301rer
dige\u0301rer
digital
digne
diluer
dimanche
diminuer
dioxyde
directif
diriger
discuter
disposer
dissiper
distance
divertir
diviser
docile
docteur
dogme
doigt
domaine
domicile
dompter
donateur
donjon
donner
dopamine
dortoir
dorure
dosage
doseur
dossier
dotation
douanier
double
douceur
douter
doyen
dragon
draper
dresser
dribbler
droiture
duperie
duplexe
durable
durcir
dynastie
e\u0301blouir
e\u0301carter
e\u0301charpe
e\u0301chelle
e\u0301clairer
e\u0301clipse
e\u0301clore
e\u0301cluse
e\u0301cole
e\u0301conomie
e\u0301corce
e\u0301couter
e\u0301craser
e\u0301cre\u0301mer
e\u0301crivain
e\u0301crou
e\u0301cume
e\u0301cureuil
e\u0301difier
e\u0301duquer
effacer
effectif
effigie
effort
effrayer
effusion
e\u0301galiser
e\u0301garer
e\u0301jecter
e\u0301laborer
e\u0301largir
e\u0301lectron
e\u0301le\u0301gant
e\u0301le\u0301phant
e\u0301le\u0300ve
e\u0301ligible
e\u0301litisme
e\u0301loge
e\u0301lucider
e\u0301luder
emballer
embellir
embryon
e\u0301meraude
e\u0301mission
emmener
e\u0301motion
e\u0301mouvoir
empereur
employer
emporter
emprise
e\u0301mulsion
encadrer
enche\u0300re
enclave
encoche
endiguer
endosser
endroit
enduire
e\u0301nergie
enfance
enfermer
enfouir
engager
engin
englober
e\u0301nigme
enjamber
enjeu
enlever
ennemi
ennuyeux
enrichir
enrobage
enseigne
entasser
entendre
entier
entourer
entraver
e\u0301nume\u0301rer
envahir
enviable
envoyer
enzyme
e\u0301olien
e\u0301paissir
e\u0301pargne
e\u0301patant
e\u0301paule
e\u0301picerie
e\u0301pide\u0301mie
e\u0301pier
e\u0301pilogue
e\u0301pine
e\u0301pisode
e\u0301pitaphe
e\u0301poque
e\u0301preuve
e\u0301prouver
e\u0301puisant
e\u0301querre
e\u0301quipe
e\u0301riger
e\u0301rosion
erreur
e\u0301ruption
escalier
espadon
espe\u0300ce
espie\u0300gle
espoir
esprit
esquiver
essayer
essence
essieu
essorer
estime
estomac
estrade
e\u0301tage\u0300re
e\u0301taler
e\u0301tanche
e\u0301tatique
e\u0301teindre
e\u0301tendoir
e\u0301ternel
e\u0301thanol
e\u0301thique
ethnie
e\u0301tirer
e\u0301toffer
e\u0301toile
e\u0301tonnant
e\u0301tourdir
e\u0301trange
e\u0301troit
e\u0301tude
euphorie
e\u0301valuer
e\u0301vasion
e\u0301ventail
e\u0301vidence
e\u0301viter
e\u0301volutif
e\u0301voquer
exact
exage\u0301rer
exaucer
exceller
excitant
exclusif
excuse
exe\u0301cuter
exemple
exercer
exhaler
exhorter
exigence
exiler
exister
exotique
expe\u0301dier
explorer
exposer
exprimer
exquis
extensif
extraire
exulter
fable
fabuleux
facette
facile
facture
faiblir
falaise
fameux
famille
farceur
farfelu
farine
farouche
fasciner
fatal
fatigue
faucon
fautif
faveur
favori
fe\u0301brile
fe\u0301conder
fe\u0301de\u0301rer
fe\u0301lin
femme
fe\u0301mur
fendoir
fe\u0301odal
fermer
fe\u0301roce
ferveur
festival
feuille
feutre
fe\u0301vrier
fiasco
ficeler
fictif
fide\u0300le
figure
filature
filetage
filie\u0300re
filleul
filmer
filou
filtrer
financer
finir
fiole
firme
fissure
fixer
flairer
flamme
flasque
flatteur
fle\u0301au
fle\u0300che
fleur
flexion
flocon
flore
fluctuer
fluide
fluvial
folie
fonderie
fongible
fontaine
forcer
forgeron
formuler
fortune
fossile
foudre
fouge\u0300re
fouiller
foulure
fourmi
fragile
fraise
franchir
frapper
frayeur
fre\u0301gate
freiner
frelon
fre\u0301mir
fre\u0301ne\u0301sie
fre\u0300re
friable
friction
frisson
frivole
froid
fromage
frontal
frotter
fruit
fugitif
fuite
fureur
furieux
furtif
fusion
futur
gagner
galaxie
galerie
gambader
garantir
gardien
garnir
garrigue
gazelle
gazon
ge\u0301ant
ge\u0301latine
ge\u0301lule
gendarme
ge\u0301ne\u0301ral
ge\u0301nie
genou
gentil
ge\u0301ologie
ge\u0301ome\u0300tre
ge\u0301ranium
germe
gestuel
geyser
gibier
gicler
girafe
givre
glace
glaive
glisser
globe
gloire
glorieux
golfeur
gomme
gonfler
gorge
gorille
goudron
gouffre
goulot
goupille
gourmand
goutte
graduel
graffiti
graine
grand
grappin
gratuit
gravir
grenat
griffure
griller
grimper
grogner
gronder
grotte
groupe
gruger
grutier
gruye\u0300re
gue\u0301pard
guerrier
guide
guimauve
guitare
gustatif
gymnaste
gyrostat
habitude
hachoir
halte
hameau
hangar
hanneton
haricot
harmonie
harpon
hasard
he\u0301lium
he\u0301matome
herbe
he\u0301risson
hermine
he\u0301ron
he\u0301siter
heureux
hiberner
hibou
hilarant
histoire
hiver
homard
hommage
homoge\u0300ne
honneur
honorer
honteux
horde
horizon
horloge
hormone
horrible
houleux
housse
hublot
huileux
humain
humble
humide
humour
hurler
hydromel
hygie\u0300ne
hymne
hypnose
idylle
ignorer
iguane
illicite
illusion
image
imbiber
imiter
immense
immobile
immuable
impact
impe\u0301rial
implorer
imposer
imprimer
imputer
incarner
incendie
incident
incliner
incolore
indexer
indice
inductif
ine\u0301dit
ineptie
inexact
infini
infliger
informer
infusion
inge\u0301rer
inhaler
inhiber
injecter
injure
innocent
inoculer
inonder
inscrire
insecte
insigne
insolite
inspirer
instinct
insulter
intact
intense
intime
intrigue
intuitif
inutile
invasion
inventer
inviter
invoquer
ironique
irradier
irre\u0301el
irriter
isoler
ivoire
ivresse
jaguar
jaillir
jambe
janvier
jardin
jauger
jaune
javelot
jetable
jeton
jeudi
jeunesse
joindre
joncher
jongler
joueur
jouissif
journal
jovial
joyau
joyeux
jubiler
jugement
junior
jupon
juriste
justice
juteux
juve\u0301nile
kayak
kimono
kiosque
label
labial
labourer
lace\u0301rer
lactose
lagune
laine
laisser
laitier
lambeau
lamelle
lampe
lanceur
langage
lanterne
lapin
largeur
larme
laurier
lavabo
lavoir
lecture
le\u0301gal
le\u0301ger
le\u0301gume
lessive
lettre
levier
lexique
le\u0301zard
liasse
libe\u0301rer
libre
licence
licorne
lie\u0300ge
lie\u0300vre
ligature
ligoter
ligue
limer
limite
limonade
limpide
line\u0301aire
lingot
lionceau
liquide
lisie\u0300re
lister
lithium
litige
littoral
livreur
logique
lointain
loisir
lombric
loterie
louer
lourd
loutre
louve
loyal
lubie
lucide
lucratif
lueur
lugubre
luisant
lumie\u0300re
lunaire
lundi
luron
lutter
luxueux
machine
magasin
magenta
magique
maigre
maillon
maintien
mairie
maison
majorer
malaxer
male\u0301fice
malheur
malice
mallette
mammouth
mandater
maniable
manquant
manteau
manuel
marathon
marbre
marchand
mardi
maritime
marqueur
marron
marteler
mascotte
massif
mate\u0301riel
matie\u0300re
matraque
maudire
maussade
mauve
maximal
me\u0301chant
me\u0301connu
me\u0301daille
me\u0301decin
me\u0301diter
me\u0301duse
meilleur
me\u0301lange
me\u0301lodie
membre
me\u0301moire
menacer
mener
menhir
mensonge
mentor
mercredi
me\u0301rite
merle
messager
mesure
me\u0301tal
me\u0301te\u0301ore
me\u0301thode
me\u0301tier
meuble
miauler
microbe
miette
mignon
migrer
milieu
million
mimique
mince
mine\u0301ral
minimal
minorer
minute
miracle
miroiter
missile
mixte
mobile
moderne
moelleux
mondial
moniteur
monnaie
monotone
monstre
montagne
monument
moqueur
morceau
morsure
mortier
moteur
motif
mouche
moufle
moulin
mousson
mouton
mouvant
multiple
munition
muraille
mure\u0300ne
murmure
muscle
muse\u0301um
musicien
mutation
muter
mutuel
myriade
myrtille
myste\u0300re
mythique
nageur
nappe
narquois
narrer
natation
nation
nature
naufrage
nautique
navire
ne\u0301buleux
nectar
ne\u0301faste
ne\u0301gation
ne\u0301gliger
ne\u0301gocier
neige
nerveux
nettoyer
neurone
neutron
neveu
niche
nickel
nitrate
niveau
noble
nocif
nocturne
noirceur
noisette
nomade
nombreux
nommer
normatif
notable
notifier
notoire
nourrir
nouveau
novateur
novembre
novice
nuage
nuancer
nuire
nuisible
nume\u0301ro
nuptial
nuque
nutritif
obe\u0301ir
objectif
obliger
obscur
observer
obstacle
obtenir
obturer
occasion
occuper
oce\u0301an
octobre
octroyer
octupler
oculaire
odeur
odorant
offenser
officier
offrir
ogive
oiseau
oisillon
olfactif
olivier
ombrage
omettre
onctueux
onduler
one\u0301reux
onirique
opale
opaque
ope\u0301rer
opinion
opportun
opprimer
opter
optique
orageux
orange
orbite
ordonner
oreille
organe
orgueil
orifice
ornement
orque
ortie
osciller
osmose
ossature
otarie
ouragan
ourson
outil
outrager
ouvrage
ovation
oxyde
oxyge\u0300ne
ozone
paisible
palace
palmare\u0300s
palourde
palper
panache
panda
pangolin
paniquer
panneau
panorama
pantalon
papaye
papier
papoter
papyrus
paradoxe
parcelle
paresse
parfumer
parler
parole
parrain
parsemer
partager
parure
parvenir
passion
paste\u0300que
paternel
patience
patron
pavillon
pavoiser
payer
paysage
peigne
peintre
pelage
pe\u0301lican
pelle
pelouse
peluche
pendule
pe\u0301ne\u0301trer
pe\u0301nible
pensif
pe\u0301nurie
pe\u0301pite
pe\u0301plum
perdrix
perforer
pe\u0301riode
permuter
perplexe
persil
perte
peser
pe\u0301tale
petit
pe\u0301trir
peuple
pharaon
phobie
phoque
photon
phrase
physique
piano
pictural
pie\u0300ce
pierre
pieuvre
pilote
pinceau
pipette
piquer
pirogue
piscine
piston
pivoter
pixel
pizza
placard
plafond
plaisir
planer
plaque
plastron
plateau
pleurer
plexus
pliage
plomb
plonger
pluie
plumage
pochette
poe\u0301sie
poe\u0300te
pointe
poirier
poisson
poivre
polaire
policier
pollen
polygone
pommade
pompier
ponctuel
ponde\u0301rer
poney
portique
position
posse\u0301der
posture
potager
poteau
potion
pouce
poulain
poumon
pourpre
poussin
pouvoir
prairie
pratique
pre\u0301cieux
pre\u0301dire
pre\u0301fixe
pre\u0301lude
pre\u0301nom
pre\u0301sence
pre\u0301texte
pre\u0301voir
primitif
prince
prison
priver
proble\u0300me
proce\u0301der
prodige
profond
progre\u0300s
proie
projeter
prologue
promener
propre
prospe\u0300re
prote\u0301ger
prouesse
proverbe
prudence
pruneau
psychose
public
puceron
puiser
pulpe
pulsar
punaise
punitif
pupitre
purifier
puzzle
pyramide
quasar
querelle
question
quie\u0301tude
quitter
quotient
racine
raconter
radieux
ragondin
raideur
raisin
ralentir
rallonge
ramasser
rapide
rasage
ratisser
ravager
ravin
rayonner
re\u0301actif
re\u0301agir
re\u0301aliser
re\u0301animer
recevoir
re\u0301citer
re\u0301clamer
re\u0301colter
recruter
reculer
recycler
re\u0301diger
redouter
refaire
re\u0301flexe
re\u0301former
refrain
refuge
re\u0301galien
re\u0301gion
re\u0301glage
re\u0301gulier
re\u0301ite\u0301rer
rejeter
rejouer
relatif
relever
relief
remarque
reme\u0300de
remise
remonter
remplir
remuer
renard
renfort
renifler
renoncer
rentrer
renvoi
replier
reporter
reprise
reptile
requin
re\u0301serve
re\u0301sineux
re\u0301soudre
respect
rester
re\u0301sultat
re\u0301tablir
retenir
re\u0301ticule
retomber
retracer
re\u0301union
re\u0301ussir
revanche
revivre
re\u0301volte
re\u0301vulsif
richesse
rideau
rieur
rigide
rigoler
rincer
riposter
risible
risque
rituel
rival
rivie\u0300re
rocheux
romance
rompre
ronce
rondin
roseau
rosier
rotatif
rotor
rotule
rouge
rouille
rouleau
routine
royaume
ruban
rubis
ruche
ruelle
rugueux
ruiner
ruisseau
ruser
rustique
rythme
sabler
saboter
sabre
sacoche
safari
sagesse
saisir
salade
salive
salon
saluer
samedi
sanction
sanglier
sarcasme
sardine
saturer
saugrenu
saumon
sauter
sauvage
savant
savonner
scalpel
scandale
sce\u0301le\u0301rat
sce\u0301nario
sceptre
sche\u0301ma
science
scinder
score
scrutin
sculpter
se\u0301ance
se\u0301cable
se\u0301cher
secouer
se\u0301cre\u0301ter
se\u0301datif
se\u0301duire
seigneur
se\u0301jour
se\u0301lectif
semaine
sembler
semence
se\u0301minal
se\u0301nateur
sensible
sentence
se\u0301parer
se\u0301quence
serein
sergent
se\u0301rieux
serrure
se\u0301rum
service
se\u0301same
se\u0301vir
sevrage
sextuple
side\u0301ral
sie\u0300cle
sie\u0301ger
siffler
sigle
signal
silence
silicium
simple
since\u0300re
sinistre
siphon
sirop
sismique
situer
skier
social
socle
sodium
soigneux
soldat
soleil
solitude
soluble
sombre
sommeil
somnoler
sonde
songeur
sonnette
sonore
sorcier
sortir
sosie
sottise
soucieux
soudure
souffle
soulever
soupape
source
soutirer
souvenir
spacieux
spatial
spe\u0301cial
sphe\u0300re
spiral
stable
station
sternum
stimulus
stipuler
strict
studieux
stupeur
styliste
sublime
substrat
subtil
subvenir
succe\u0300s
sucre
suffixe
sugge\u0301rer
suiveur
sulfate
superbe
supplier
surface
suricate
surmener
surprise
sursaut
survie
suspect
syllabe
symbole
syme\u0301trie
synapse
syntaxe
syste\u0300me
tabac
tablier
tactile
tailler
talent
talisman
talonner
tambour
tamiser
tangible
tapis
taquiner
tarder
tarif
tartine
tasse
tatami
tatouage
taupe
taureau
taxer
te\u0301moin
temporel
tenaille
tendre
teneur
tenir
tension
terminer
terne
terrible
te\u0301tine
texte
the\u0300me
the\u0301orie
the\u0301rapie
thorax
tibia
tie\u0300de
timide
tirelire
tiroir
tissu
titane
titre
tituber
toboggan
tole\u0301rant
tomate
tonique
tonneau
toponyme
torche
tordre
tornade
torpille
torrent
torse
tortue
totem
toucher
tournage
tousser
toxine
traction
trafic
tragique
trahir
train
trancher
travail
tre\u0300fle
tremper
tre\u0301sor
treuil
triage
tribunal
tricoter
trilogie
triomphe
tripler
triturer
trivial
trombone
tronc
tropical
troupeau
tuile
tulipe
tumulte
tunnel
turbine
tuteur
tutoyer
tuyau
tympan
typhon
typique
tyran
ubuesque
ultime
ultrason
unanime
unifier
union
unique
unitaire
univers
uranium
urbain
urticant
usage
usine
usuel
usure
utile
utopie
vacarme
vaccin
vagabond
vague
vaillant
vaincre
vaisseau
valable
valise
vallon
valve
vampire
vanille
vapeur
varier
vaseux
vassal
vaste
vecteur
vedette
ve\u0301ge\u0301tal
ve\u0301hicule
veinard
ve\u0301loce
vendredi
ve\u0301ne\u0301rer
venger
venimeux
ventouse
verdure
ve\u0301rin
vernir
verrou
verser
vertu
veston
ve\u0301te\u0301ran
ve\u0301tuste
vexant
vexer
viaduc
viande
victoire
vidange
vide\u0301o
vignette
vigueur
vilain
village
vinaigre
violon
vipe\u0300re
virement
virtuose
virus
visage
viseur
vision
visqueux
visuel
vital
vitesse
viticole
vitrine
vivace
vivipare
vocation
voguer
voile
voisin
voiture
volaille
volcan
voltiger
volume
vorace
vortex
voter
vouloir
voyage
voyelle
wagon
xe\u0301non
yacht
ze\u0300bre
ze\u0301nith
zeste
zoologie`.split("\n");

// scripts/derive-wallets.mjs
var import_node_crypto = require("node:crypto");
var ALPHABET = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";
function base58Encode(buffer) {
  let n = BigInt("0x" + Buffer.from(buffer).toString("hex"));
  let out = "";
  while (n > 0n) {
    const r = Number(n % 58n);
    n /= 58n;
    out = ALPHABET[r] + out;
  }
  for (const byte of buffer) {
    if (byte !== 0) break;
    out = "1" + out;
  }
  return out || "1";
}
function sha2563(buffer) {
  return (0, import_node_crypto.createHash)("sha256").update(buffer).digest();
}
function base58check(payload) {
  return base58Encode(Buffer.concat([payload, sha2563(sha2563(payload)).subarray(0, 4)]));
}
function tronAddressFromPublicKey(publicKeyHex) {
  const publicKey = Buffer.from(getBytes(publicKeyHex));
  const hash2 = Buffer.from(getBytes(keccak256(publicKey.subarray(1))));
  const payload = Buffer.concat([Buffer.from([65]), hash2.subarray(12)]);
  return base58check(payload);
}
function makeFrenchWordlist(words7) {
  const map = /* @__PURE__ */ new Map();
  words7.forEach((w, i) => map.set(w.normalize("NFKD"), i));
  return {
    locale: "fr",
    getWordIndex(word) {
      const key = String(word).toLowerCase().normalize("NFKD");
      return map.has(key) ? map.get(key) : -1;
    },
    getWord(index) {
      return words7[index];
    },
    split(phrase) {
      return String(phrase).toLowerCase().split(/\s+/g);
    },
    join(words8) {
      return words8.join(" ");
    }
  };
}
async function readStdin() {
  let data4 = "";
  process.stdin.setEncoding("utf8");
  for await (const chunk of process.stdin) data4 += chunk;
  return data4.trim();
}
function parseArgs() {
  const args = process.argv.slice(2);
  const get = (name, fallback) => {
    const i = args.indexOf(name);
    return i >= 0 && args[i + 1] !== void 0 ? args[i + 1] : fallback;
  };
  return {
    index: Number(get("--index", process.env.INDEX || "0")),
    lang: String(get("--lang", process.env.BIP39_LANGUAGE || "en")).toLowerCase(),
    json: args.includes("--json")
  };
}
async function main() {
  const { index, lang, json } = parseArgs();
  if (!Number.isInteger(index) || index < 0) {
    throw new Error("INDEX invalide (entier >= 0 attendu).");
  }
  let phrase = (process.env.SEED_PHRASE || "").trim();
  if (!phrase && !process.stdin.isTTY) {
    phrase = await readStdin();
  }
  if (!phrase) {
    console.error('Usage : SEED_PHRASE="mot1 mot2 ..." node scripts/derive-wallets.mjs [--index 0] [--json]');
    console.error("Ou : node scripts/derive-wallets.mjs < seed.txt");
    console.error("Ne jamais coller une seed r\xE9elle dans un shell partag\xE9/historis\xE9.");
    process.exit(1);
  }
  phrase = phrase.replace(/\s+/g, " ").trim();
  const passphrase = process.env.BIP39_PASSPHRASE || "";
  const evmPath = `m/44'/60'/0'/0/${index}`;
  const tronPath = `m/44'/195'/0'/0/${index}`;
  const wordlist10 = lang === "fr" ? makeFrenchWordlist(wordlist9) : wordlists2.en;
  if (!wordlist10) {
    console.error("Langue BIP39 non support\xE9e (utilise --lang fr ou --lang en).");
    process.exit(1);
  }
  let evm;
  let tron;
  try {
    if (!Mnemonic.isValidMnemonic(phrase, wordlist10)) {
      throw new Error("checksum");
    }
    evm = HDNodeWallet.fromPhrase(phrase, passphrase, evmPath, wordlist10);
    tron = HDNodeWallet.fromPhrase(phrase, passphrase, tronPath, wordlist10);
  } catch (e) {
    console.error(`Seed phrase BIP39 ${lang === "fr" ? "fran\xE7aise" : "anglaise"} invalide (mots/checksum/ordre).`);
    process.exit(1);
  }
  const result = {
    language: lang,
    index,
    evm: {
      path: evmPath,
      address: evm.address,
      publicKey: evm.signingKey.publicKey,
      privateKey: evm.privateKey
    },
    tron: {
      path: tronPath,
      address: tronAddressFromPublicKey(tron.signingKey.publicKey),
      publicKey: tron.signingKey.publicKey,
      privateKey: tron.privateKey
    }
  };
  if (json) {
    console.log(JSON.stringify(result, null, 2));
    return;
  }
  console.log("\u26A0\uFE0F  SECRETS : ne jamais partager ni committer ces cl\xE9s.");
  console.log(`Langue : ${lang}`);
  console.log(`Index : ${index}`);
  console.log("");
  console.log("EVM (BNB / Polygon / Pingala / MetaMask)");
  console.log(`  path       : ${result.evm.path}`);
  console.log(`  address    : ${result.evm.address}`);
  console.log(`  publicKey  : ${result.evm.publicKey}`);
  console.log(`  privateKey : ${result.evm.privateKey}`);
  console.log("");
  console.log("Tron (TRC20)");
  console.log(`  path       : ${result.tron.path}`);
  console.log(`  address    : ${result.tron.address}`);
  console.log(`  publicKey  : ${result.tron.publicKey}`);
  console.log(`  privateKey : ${result.tron.privateKey}`);
}
main().catch((e) => {
  console.error("Erreur :", e.message);
  process.exit(1);
});
/*! Bundled license information:

@noble/hashes/esm/utils.js:
  (*! noble-hashes - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/utils.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/modular.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/curve.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/abstract/weierstrass.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/_shortw_utils.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

@noble/curves/esm/secp256k1.js:
  (*! noble-curves - MIT License (c) 2022 Paul Miller (paulmillr.com) *)

aes-js/lib.esm/aes.js:
  (*! MIT License. Copyright 2015-2022 Richard Moore <me@ricmoo.com>. See LICENSE.txt. *)
*/
