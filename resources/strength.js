// Dimuat hanya saat pengguna mulai mengetik password baru.
import { ZxcvbnFactory } from '@zxcvbn-ts/core';
import { adjacencyGraphs, dictionary } from '@zxcvbn-ts/language-common';

const estimator = new ZxcvbnFactory({ graphs: adjacencyGraphs, dictionary });
const common = new Set(dictionary['passwords-common']);

/** Sama dengan PasswordPolicy::isCommon. */
const isCommon = (value) => {
  const text = value.toLowerCase();
  const bare = text.replace(/^[^\p{L}]+|[^\p{L}]+$/gu, '');
  return common.has(text) || (bare !== '' && [...value].length - [...bare].length <= 6 && common.has(bare));
};

export const strength = (value, words) => ({ score: estimator.check(value.slice(0, 72), words).score, common: isCommon(value) });
